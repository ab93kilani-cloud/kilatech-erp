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
        // 1. Sales Today (based on invoices created today)
        $salesToday = Invoice::whereDate('created_at', Carbon::today())
            ->sum('total_amount');

        // 2. Stock Alerts (Low stock and Expiry)
        // Count variants where available stock < 10
        $lowStockCount = ProductVariant::whereHas('product')
            ->withCount(['units' => function($q) {
                $q->where('status', 'available');
            }])
            ->get()
            ->filter(function($variant) {
                return $variant->units_count < 10;
            })
            ->count();

        // Expiring lots in next 30 days
        $expiringLotsCount = Lot::where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->where('expiration_date', '>=', Carbon::today())
            ->count();
        
        $stockAlertsCount = $lowStockCount + $expiringLotsCount;

        // 3. Pending Quotes
        $pendingQuotesCount = Quote::whereIn('status', ['draft', 'pending_approval'])
            ->count();

        // 4. QA Receptions (Number of receipts with pending QA lines)
        $qaReceptionsCount = ReceiptLine::where('status', 'pending_qa')
            ->distinct('receipt_id')
            ->count('receipt_id');

        // 5. Marketing Articles in Draft
        $marketingPendingCount = Article::where('status', 'draft')
            ->count();

        // 6. Missing Supplier Invoices
        $missingInvoicesCount = Receipt::where('invoice_status', 'pending')->count();

        // Send a daily-like notification if missing invoices exist (simplified check)
        if ($missingInvoicesCount > 0) {
            $existing = \App\Models\InternalNotification::where('title', 'LIKE', '%Factures Fournisseur Manquantes%')
                ->whereDate('created_at', Carbon::today())
                ->exists();
            
            if (!$existing) {
                \App\Models\InternalNotification::create([
                    'title' => '🚨 Factures Fournisseur Manquantes (' . $missingInvoicesCount . ')',
                    'message' => 'Il y a ' . $missingInvoicesCount . ' bons de réception sans facture PDF attachée. Veuillez les uploader dans les Archives Achats.',
                    'action_url' => '/admin/purchases?archived=1',
                    'type' => 'warning'
                ]);
            }
        }

        return response()->json([
            'sales_today' => (float)$salesToday,
            'stock_alerts' => $stockAlertsCount,
            'pending_quotes' => $pendingQuotesCount,
            'qa_receptions' => $qaReceptionsCount,
            'marketing_pending' => $marketingPendingCount,
            'missing_invoices' => $missingInvoicesCount,
        ]);
    }
}
