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

        // 1. --- CHIFFRE D'AFFAIRES ---
        // IMPORTANT: withTrashed() car les factures payées sont soft-deleted (archivées)
        $salesToday  = Invoice::withTrashed()->whereDate(DB::raw('IFNULL(invoice_date, created_at)'), $today)->sum('total_amount');
        $salesMonth  = Invoice::withTrashed()->whereBetween(DB::raw('IFNULL(invoice_date, created_at)'), [$startOfMonth, Carbon::now()])->sum('total_amount');
        $salesYear   = Invoice::withTrashed()->whereYear(DB::raw('IFNULL(invoice_date, created_at)'), $today->year)->sum('total_amount');

        // 2. --- TRÉSORERIE ---
        $totalInvoiced = Invoice::withTrashed()->sum('total_amount');
        $totalPaid     = Invoice::withTrashed()->sum(DB::raw('total_amount - balance_due'));
        $totalUnpaid   = Invoice::withTrashed()->sum('balance_due');

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
            
            // Facturé (Ventes)
            $monthInvoiced = Invoice::withTrashed()
                ->whereYear(DB::raw('IFNULL(invoice_date, created_at)'), $monthDate->year)
                ->whereMonth(DB::raw('IFNULL(invoice_date, created_at)'), $monthDate->month)
                ->sum('total_amount');
            
            // Devis (Opportunités)
            $monthQuotes = Quote::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->sum('total_amount');
            
            // Encaissé (Trésorerie réelle)
            $monthPaid = Invoice::withTrashed()
                ->whereYear(DB::raw('IFNULL(invoice_date, created_at)'), $monthDate->year)
                ->whereMonth(DB::raw('IFNULL(invoice_date, created_at)'), $monthDate->month)
                ->sum(DB::raw('total_amount - balance_due'));
            
            $chartData[] = [
                'label'    => $monthDate->translatedFormat('M Y'),
                'invoiced' => (float)$monthInvoiced,
                'quotes'   => (float)$monthQuotes,
                'paid'     => (float)$monthPaid
            ];
        }

        // 5. --- ALERTES ---
        $lowStockCount = ProductVariant::whereHas('product')
            ->withCount(['units' => function($q) { $q->where('status', 'disponible'); }])
            ->get()->filter(function($v) { return $v->units_count < 10; })->count();
        
        $expiringLotsCount = Lot::where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->where('expiration_date', '>=', Carbon::today())
            ->whereHas('units', function($q) { $q->where('status', 'disponible'); })
            ->count();

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
