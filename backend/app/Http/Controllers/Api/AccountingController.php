<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\BankStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use ZipArchive;

class AccountingController extends Controller
{
    public function dashboard(Request $request)
    {
        $quarter = $request->query('quarter', ceil(date('n') / 3));
        $year = $request->query('year', date('Y'));

        $months = match((int)$quarter) {
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12],
            default => [1, 2, 3]
        };

        $salesInvoices = Invoice::withTrashed()
            ->where(function ($query) use ($year, $months) {
                $query->whereYear(DB::raw('COALESCE(invoice_date, created_at)'), $year)
                      ->whereIn(DB::raw('MONTH(COALESCE(invoice_date, created_at))'), $months);
            })
            ->with(['order' => fn($q) => $q->withTrashed(), 'order.client' => fn($q) => $q->withTrashed()])
            ->get();

        $purchaseInvoices = Receipt::withTrashed()
            ->whereNotNull('invoice_path')
            ->whereHas('purchaseOrder', function ($query) use ($year, $months) {
                $query->withTrashed()
                      ->whereYear('po_date', $year)
                      ->whereIn(DB::raw('MONTH(po_date)'), $months);
            })
            ->with(['purchaseOrder' => fn($q) => $q->withTrashed()->with('lines.variant'), 'purchaseOrder.supplier'])
            ->get();

        $bankStatements = BankStatement::where('year', $year)
            ->whereIn('month', $months)
            ->with('uploader')
            ->get();

        $suppliersMissingCertificates = Supplier::whereNull('tax_certificate_path')
            ->orWhere('tax_certificate_expires_at', '<', now())
            ->get();

        $suppliersWithCertificates = Supplier::whereNotNull('tax_certificate_path')
            ->get();

        $payments = InvoicePayment::with(['invoice' => fn($q) => $q->withTrashed()->with(['order.client' => fn($q) => $q->withTrashed()])])
            ->where(function ($query) use ($year, $months) {
                $query->whereYear('payment_date', $year)
                      ->whereIn(DB::raw('MONTH(payment_date)'), $months);
            })->get();

        $overdueSalesCount = Invoice::where('balance_due', '>', 0)
            ->where('due_date', '<', now())
            ->count();

        $overduePurchaseCount = PurchaseOrder::where('status', '!=', 'paid') // Assuming status tracking for POs
            ->where('due_date', '<', now())
            ->count();

        return response()->json([
            'sales_invoices' => $salesInvoices,
            'purchase_invoices' => $purchaseInvoices,
            'bank_statements' => $bankStatements,
            'suppliers_missing_certificates' => $suppliersMissingCertificates,
            'suppliers_with_certificates' => $suppliersWithCertificates,
            'payments' => $payments,
            'overdue_sales_count' => $overdueSalesCount,
            'overdue_purchase_count' => $overduePurchaseCount
        ]);
    }

    public function uploadBankStatement(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
        ]);

        $path = $request->file('file')->store('bank_statements', 'public');

        $statement = BankStatement::create([
            'file_path' => $path,
            'month' => $request->month,
            'year' => $request->year,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($statement, 201);
    }

    public function uploadSupplierCertificate(Request $request, $supplierId)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
            'expires_at' => 'required|date',
        ]);

        $supplier = Supplier::findOrFail($supplierId);
        $path = $request->file('file')->store('supplier_certificates', 'public');

        $supplier->update([
            'tax_certificate_path' => $path,
            'tax_certificate_expires_at' => $request->expires_at,
        ]);

        return response()->json($supplier);
    }
    public function financeOverview()
    {
        // Tolérance de 0.50 DH pour ignorer les écarts d'arrondis
        $threshold = 0.5;

        $unpaidInvoices = Invoice::where('balance_due', '>=', $threshold)
            ->with(['order.client'])
            ->orderBy('due_date', 'asc')
            ->get();

        $unpaidPurchases = PurchaseOrder::withTrashed()
            ->with(['supplier', 'lines.variant', 'payments'])
            ->orderBy('due_date', 'asc')
            ->get()
            ->filter(function($po) use ($threshold) {
                // On garde les commandes (non brouillon) :
                // 1. Qui ont un reste à payer significatif (> 0.50 DH)
                // 2. OU qui ont des paiements par chèque/effet non encore confirmés manuellement (même si la date d'échéance est passée)
                $hasPendingChecks = $po->payments->contains(function($pay) {
                    return $pay->maturity_date && !$pay->cashed_at;
                });

                return $po->status !== 'draft' && ($po->balance_due >= $threshold || $hasPendingChecks);
            })
            ->values();

        return response()->json([
            'unpaid_invoices' => $unpaidInvoices,
            'unpaid_purchases' => $unpaidPurchases
        ]);
    }

    public function quickUpdateDueDate(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:invoice,purchase',
            'due_date' => 'required|date'
        ]);

        if ($request->type === 'invoice') {
            \App\Models\Invoice::where('id', $request->id)->update(['due_date' => $request->due_date]);
        } else {
            \App\Models\PurchaseOrder::where('id', $request->id)->update(['due_date' => $request->due_date]);
        }

        return response()->json(['message' => 'Date d\'échéance mise à jour']);
    }
}
