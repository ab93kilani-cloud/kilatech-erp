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
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function getStats()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. --- ANALYSE COMMERCIALE (VENTES) ---
        // Les invoices ne sont jamais soft-deleted directement, on utilise la date de création comme fallback

        // Aujourd'hui : invoice_date OU created_at
        $salesToday = Invoice::whereDate('created_at', $today)->sum('total_amount');
        
        // Mois en cours
        $salesMonth = Invoice::whereBetween('created_at', [$startOfMonth, Carbon::now()])->sum('total_amount');
        
        // Année en cours
        $salesYear = Invoice::whereYear('created_at', $today->year)->sum('total_amount');

        // 2. --- ANALYSE TRÉSORERIE ---
        $totalInvoiced = Invoice::sum('total_amount');
        $totalPaid     = Invoice::sum(DB::raw('total_amount - balance_due'));
        $totalUnpaid   = Invoice::sum('balance_due');

        $recoveryWarning = $totalInvoiced > 0 && ($totalUnpaid > ($totalInvoiced * 0.25));

        // 3. --- TOP 10 PRODUITS (MARGE BÉNÉFICIAIRE) ---
        $topProducts = DB::table('order_lines')
            ->join('product_variants', 'order_lines.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('SUM(order_lines.quantity) as total_qty'),
                DB::raw('SUM((order_lines.unit_price - product_variants.cost_price) * order_lines.quantity) as total_profit')
            )
            ->whereNull('product_variants.deleted_at')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_profit', 'desc')
            ->limit(10)
            ->get();

        // 4. --- DONNÉES GRAPHIQUE (12 DERNIERS MOIS) ---
        $chartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthSales = Invoice::where(function($q) use ($monthDate) {
                $q->whereYear('invoice_date', $monthDate->year)->whereMonth('invoice_date', $monthDate->month)
                  ->orWhere(function($sub) use ($monthDate) {
                      $sub->whereNull('invoice_date')->whereYear('created_at', $monthDate->year)->whereMonth('created_at', $monthDate->month);
                  });
            })->sum('total_amount');
            
            $chartData[] = [
                'label' => $monthDate->translatedFormat('M Y'),
                'value' => (float)$monthSales
            ];
        }

        // 5. --- ALERTES ---
        $lowStockCount = ProductVariant::whereHas('product')
            ->withCount(['units' => function($q) { $q->where('status', 'available'); }])
            ->get()->filter(function($v) { return $v->units_count < 10; })->count();

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
            'stock_alerts' => $lowStockCount + Lot::where('expiration_date', '<=', Carbon::today()->addDays(30))->where('expiration_date', '>=', Carbon::today())->count(),
            'pending_quotes' => Quote::whereIn('status', ['draft', 'pending_approval'])->count(),
            'qa_receptions' => ReceiptLine::where('status', 'pending_qa')->distinct('receipt_id')->count('receipt_id'),
            'marketing_pending' => Article::where('status', 'draft')->count(),
        ]);
    }
}
