<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\Article;
use App\Models\StockUnit;
use App\Models\Lot;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        // --- VENTES (Factures ou BL si factures vides) ---
        $salesToday = Invoice::where(function($q) use ($today) {
            $q->whereDate('invoice_date', $today)->orWhere(function($sub) use ($today) {
                $sub->whereNull('invoice_date')->whereDate('created_at', $today);
            });
        })->sum('total_amount');
        
        if ($salesToday == 0) {
            $salesToday = DB::table('order_lines')
                ->join('orders', 'order_lines.order_id', '=', 'orders.id')
                ->whereNull('orders.deleted_at')
                ->where(function($q) use ($today) {
                    $q->whereDate('orders.order_date', $today)->orWhere(function($sub) use ($today) {
                        $sub->whereNull('orders.order_date')->whereDate('orders.created_at', $today);
                    });
                })->sum(DB::raw('unit_price * quantity'));
        }

        $salesMonth = Invoice::where(function($q) use ($startOfMonth) {
            $q->whereBetween('invoice_date', [$startOfMonth, Carbon::now()])
              ->orWhere(function($sub) use ($startOfMonth) {
                  $sub->whereNull('invoice_date')->whereBetween('created_at', [$startOfMonth, Carbon::now()]);
              });
        })->sum('total_amount');
        
        if ($salesMonth == 0) {
             $salesMonth = DB::table('order_lines')
                ->join('orders', 'order_lines.order_id', '=', 'orders.id')
                ->whereNull('orders.deleted_at')
                ->where(function($q) use ($startOfMonth) {
                    $q->whereBetween('orders.order_date', [$startOfMonth, Carbon::now()])
                      ->orWhere(function($sub) use ($startOfMonth) {
                          $sub->whereNull('orders.order_date')->whereBetween('orders.created_at', [$startOfMonth, Carbon::now()]);
                      });
                })->sum(DB::raw('unit_price * quantity'));
        }

        $salesYear = Invoice::whereYear('created_at', $today->year)->sum('total_amount');

        // --- ACHATS (PurchaseOrders via PoLines) ---
        $purchasesToday = DB::table('po_lines')
            ->join('purchase_orders', 'po_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereDate('purchase_orders.created_at', $today)
            ->sum(DB::raw('unit_price * ordered_quantity'));

        $purchasesMonth = DB::table('po_lines')
            ->join('purchase_orders', 'po_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereYear('purchase_orders.created_at', $today->year)
            ->whereMonth('purchase_orders.created_at', $today->month)
            ->sum(DB::raw('unit_price * ordered_quantity'));

        $purchasesYear = DB::table('po_lines')
            ->join('purchase_orders', 'po_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereYear('purchase_orders.created_at', $today->year)
            ->sum(DB::raw('unit_price * ordered_quantity'));

        // --- ALERTES & FLUX ---
        $lowStockCount = ProductVariant::whereHas('product')
            ->withCount(['units' => function($q) { $q->where('status', 'available'); }])
            ->get()->filter(function($v) { return $v->units_count < 10; })->count();

        $expiringLotsCount = Lot::where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->where('expiration_date', '>=', Carbon::today())->count();
        
        $pendingQuotesCount = Quote::whereIn('status', ['draft', 'pending_approval'])->count();
        $qaReceptionsCount = ReceiptLine::where('status', 'pending_qa')->distinct('receipt_id')->count('receipt_id');
        $marketingPendingCount = Article::where('status', 'draft')->count();

        // Missing Supplier Invoices (Archives)
        $missingInvoicesCount = Receipt::where('invoice_status', 'pending')->count();

        // Notification Automatique si manquants
        if ($missingInvoicesCount > 0) {
            $existing = \App\Models\InternalNotification::where('title', 'LIKE', '%Manquantes%')
                ->whereDate('created_at', Carbon::today())->exists();
            if (!$existing) {
                \App\Models\InternalNotification::create([
                    'title' => '🚨 Factures Fournisseur Manquantes (' . $missingInvoicesCount . ')',
                    'message' => 'Il manque des factures PDF pour ' . $missingInvoicesCount . ' réceptions.',
                    'action_url' => '/admin/purchases?archived=1',
                    'type' => 'warning'
                ]);
            }
        }

        return response()->json([
            'sales' => [
                'today' => (float)$salesToday,
                'month' => (float)$salesMonth,
                'year' => (float)$salesYear,
            ],
            'purchases' => [
                'today' => (float)$purchasesToday,
                'month' => (float)$purchasesMonth,
                'year' => (float)$purchasesYear,
            ],
            'stock_alerts' => $lowStockCount + $expiringLotsCount,
            'low_stock' => $lowStockCount,
            'expiring_soon' => $expiringLotsCount,
            'pending_quotes' => $pendingQuotesCount,
            'qa_receptions' => $qaReceptionsCount,
            'marketing_pending' => $marketingPendingCount,
            'missing_invoices' => $missingInvoicesCount,
        ]);
    }
}
