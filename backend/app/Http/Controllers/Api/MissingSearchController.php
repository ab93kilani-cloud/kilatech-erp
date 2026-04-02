<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissingSearch;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MissingSearchController extends Controller
{
    /**
     * Get all missing searches for the manager dashboard.
     */
    public function index()
    {
        return MissingSearch::orderBy('count', 'desc')
            ->orderBy('last_seen_at', 'desc')
            ->get();
    }

    /**
     * Log a search query that yielded no results.
     */
    public function log(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1|max:100',
        ]);

        $query = strtolower(trim($request->query('query')));

        $missingSearch = MissingSearch::firstOrCreate(
            ['query' => $query],
            ['count' => 0]
        );

        $missingSearch->increment('count');
        $missingSearch->last_seen_at = Carbon::now();
        $missingSearch->save();

        return response()->json(['status' => 'success']);
    }

    /**
     * Clear a specific search query from the list once addressed.
     */
    public function destroy(MissingSearch $missingSearch)
    {
        $missingSearch->delete();
        return response()->json(null, 204);
    }

    public function configStatus()
    {
        return response()->json([
            'is_configured' => !empty(config('services.marketing_ai.api_key')),
            'provider' => 'OpenAI'
        ]);
    }

    public function repairDb()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'status' => 'success',
                'message' => 'Migration réussie !',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
