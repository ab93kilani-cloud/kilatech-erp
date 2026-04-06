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
        $startOfYear = Carbon::now()->startOfYear();

        // 1. --- ANALYSE COMMERCIALE (VENTES) ---
        // On cherche par invoice_date OU created_at si invoice_date est nul
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
        if ($salesYear == 0) {
             $salesYear = DB::table('order_lines')
                ->join('orders', 'order_lines.order_id', '=', 'orders.id')
                ->whereNull('orders.deleted_at')
                ->whereYear('orders.created_at', $today->year)
                ->sum(DB::raw('unit_price * quantity'));
        }

        // 2. --- ANALYSE TRÉSORERIE (PAIEMENTS vs IMPAYÉS) ---
        $totalInvoiced = Invoice::sum('total_amount');
        $totalPaid = Invoice::where('status', 'paid')->sum('total_amount');
        $totalUnpaid = Invoice::sum('balance_due');
        
        $recoveryWarning = ($totalUnpaid > ($totalInvoiced * 0.25)) ? true : false;

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
