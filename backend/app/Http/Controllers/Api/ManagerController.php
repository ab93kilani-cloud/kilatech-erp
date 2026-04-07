<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\Quote;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ManagerDocument;
use Illuminate\Support\Facades\Storage;
class ManagerController extends Controller
{
    public function stats(Request $request)
    {
        $year = $request->query('year', date('Y'));
        
        // Revenue from Invoices
        $revenue = Invoice::whereYear('created_at', $year)->sum('total_amount');
        
        // Purchase costs from Receipts (based on actual_purchase_price in receipt lines)
        $purchases = DB::table('receipt_lines')
            ->join('receipts', 'receipts.id', '=', 'receipt_lines.receipt_id')
            ->whereYear('receipts.receipt_date', $year)
            ->sum(DB::raw('received_quantity * actual_purchase_price'));

        // Current Stock Value
        $stockValue = DB::table('stock_units')
            ->join('lots', 'lots.id', '=', 'stock_units.lot_id')
            ->whereNull('stock_units.deleted_at')
            ->where('stock_units.status', 'disponible')
            ->sum('lots.actual_purchase_price');

        // Payment status
        $totalCollected = DB::table('invoice_payments')->sum('amount');
        $totalDue = Invoice::sum('balance_due');

        return response()->json([
            'revenue' => (float)$revenue,
            'purchases' => (float)$purchases,
            'gross_margin' => (float)($revenue - $purchases),
            'stock_worth' => (float)$stockValue,
            'collection_rate' => $revenue > 0 ? round(($totalCollected / $revenue) * 100, 2) : 0,
            'total_due' => (float)$totalDue
        ]);
    }

    public function performance(Request $request)
    {
        // Get all active users
        $users = User::with('role')->where('is_active', true)->get();

        $performance = $users->map(function($user) {
            // Quotes Locked
            $quotesLocked = Quote::where('locked_by_user_id', $user->id)->count();
            
            // Orders finalized (from Quotes they created/locked)
            $ordersCount = DB::table('orders')
                ->join('quotes', 'quotes.id', '=', 'orders.quote_id')
                ->where('quotes.locked_by_user_id', $user->id)
                ->count();

            // Receipts processed
            $receiptsCount = Receipt::where('received_by', $user->id)->count();

            // General Activity from Logs (Last 30 days)
            $logsCount = AuditLog::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->name,
                'quotes_locked' => $quotesLocked,
                'orders_processed' => $ordersCount,
                'receipts_handled' => $receiptsCount,
                'activity_index' => $logsCount
            ];
        });

        return response()->json($performance);
    }

    public function getDocuments(Request $request)
    {
        $type = $request->query('type');
        $query = ManagerDocument::with('uploader');
        
        if ($type) {
            $query->where('type', $type);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'type' => 'required|in:purchase_invoice,rent_receipt,salary_cnss',
            'title' => 'nullable|string',
            'month' => 'nullable|integer',
            'year' => 'nullable|integer',
            'amount' => 'nullable|numeric',
        ]);

        $path = $request->file('file')->store('manager_documents', 'public');

        $document = ManagerDocument::create([
            'title' => $request->title,
            'type' => $request->type,
            'month' => $request->month,
            'year' => $request->year,
            'amount' => $request->amount,
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($document->load('uploader'), 201);
    }

    public function deleteDocument($id)
    {
        $document = ManagerDocument::findOrFail($id);
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
        
        return response()->json(['message' => 'Document deleted successfully']);
    }
}
