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

        // 1. --- ANALYSE COMMERCIALE (VENTES) ---
        $salesToday = Invoice::whereYear('invoice_date', $today->year)->whereMonth('invoice_date', $today->month)->whereDay('invoice_date', $today->day)->sum('total_amount');
        if ($salesToday == 0) {
             $salesToday = DB::table('order_lines')
                ->join('orders', 'order_lines.order_id', '=', 'orders.id')
                ->whereNull('orders.deleted_at')
                ->whereDate('orders.order_date', $today)
                ->sum(DB::raw('unit_price * quantity'));
        }

        $salesMonth = Invoice::whereYear('invoice_date', $today->year)->whereMonth('invoice_date', $today->month)->sum('total_amount');
        $salesYear = Invoice::whereYear('invoice_date', $today->year)->sum('total_amount');

        // 2. --- ANALYSE TRÉSORERIE (PAIEMENTS vs IMPAYÉS) ---
        $totalInvoiced = Invoice::sum('total_amount');
        $totalPaid = Invoice::where('status', 'paid')->sum('total_amount');
        // On peut aussi calculer précisément via balance_due
        $totalUnpaid = Invoice::sum('balance_due');
        
        $recoveryWarning = ($totalUnpaid > ($totalInvoiced * 0.25)) ? true : false;

        // 3. --- ANALYSE ACHATS ---
        $purchasesYear = DB::table('po_lines')
            ->join('purchase_orders', 'po_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereYear('purchase_orders.created_at', $today->year)
            ->sum(DB::raw('unit_price * ordered_quantity'));

        // 4. --- TOP 10 PRODUITS (PAR MARGE BÉNÉFICIAIRE) ---
        // On calcule (Prix de vente HT - Prix de revient) * Quantité vendue
        $topProducts = DB::table('order_lines')
            ->join('product_variants', 'order_lines.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('SUM(order_lines.quantity) as total_qty'),
                DB::raw('SUM((order_lines.unit_price - product_variants.cost_price) * order_lines.quantity) as total_profit')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_profit', 'desc')
            ->limit(10)
            ->get();

        // 5. --- DONNÉES GRAPHIQUE (12 DERNIERS MOIS) ---
        $chartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthSales = Invoice::whereYear('invoice_date', $monthDate->year)
                ->whereMonth('invoice_date', $monthDate->month)
                ->sum('total_amount');
            
            $chartData[] = [
                'label' => $monthDate->translatedFormat('M Y'),
                'value' => (float)$monthSales
            ];
        }

        // --- ALERTES OPÉRATIONNELLES ---
        $lowStockCount = ProductVariant::whereHas('product')
            ->withCount(['units' => function($q) { $q->where('status', 'available'); }])
            ->get()->filter(function($v) { return $v->units_count < 10; })->count();

        $expiringLotsCount = Lot::where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->where('expiration_date', '>=', Carbon::today())->count();
        
        return response()->json([
            'sales' => [
                'today' => (float)$salesToday,
                'month' => (float)$salesMonth,
                'year' => (float)$salesYear,
            ],
            'treasury' => [
                'paid' => (float)$totalPaid,
                'unpaid' => (float)$totalUnpaid,
                'warning' => $recoveryWarning
            ],
            'top_products' => $topProducts,
            'chart' => $chartData,
            'stock_alerts' => $lowStockCount + $expiringLotsCount,
            'pending_quotes' => Quote::whereIn('status', ['draft', 'pending_approval'])->count(),
            'qa_receptions' => ReceiptLine::where('status', 'pending_qa')->distinct('receipt_id')->count('receipt_id'),
            'marketing_pending' => Article::where('status', 'draft')->count(),
        ]);
    }
}
