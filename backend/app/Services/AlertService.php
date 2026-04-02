<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Notification;
use App\Models\ProductVariant;
use Carbon\Carbon;

class AlertService
{
    /**
     * Scan the database for business alerts and generate Notifications
     */
    public function generateAlerts()
    {
        $this->checkExpiringLots();
        $this->checkLowStock();
    }

    private function checkExpiringLots()
    {
        // Lots expiring in the next 30 days
        $expiringLots = Lot::where('expiration_date', '<=', Carbon::now()->addDays(30))
            ->where('expiration_date', '>=', Carbon::now()) // Prevent too old duplicates 
            ->whereHas('units', function ($q) {
                $q->where('status', 'disponible'); // Only if we still have available stock
            })
            ->with(['variant.product'])
            ->get();

        foreach ($expiringLots as $lot) {
            $productName = $lot->variant->product->name ?? 'Produit Inconnu';
            $daysLeft = Carbon::parse($lot->expiration_date)->diffInDays(now());
            
            // Check if alert already exists recently
            $exists = Notification::where('type', 'expiring_lot')
                ->where('title', 'like', "%$lot->lot_number%")
                ->where('created_at', '>', Carbon::now()->subDays(7))
                ->exists();

            if (!$exists) {
                Notification::create([
                    'user_id' => null, // Global Alert
                    'type' => 'expiring_lot',
                    'title' => "DLC Proche : Lot #$lot->lot_number",
                    'message' => "Le lot $lot->lot_number de $productName expire dans $daysLeft jours (" . $lot->expiration_date . ").",
                    'action_url' => "/inventory/lots/{$lot->id}"
                ]);
            }
        }
    }

    private function checkLowStock()
    {
        // We consider a stock low if we have less than 10 available units (Threshold could be dynamic per variant later)
        $variants = ProductVariant::withCount(['units' => function ($query) {
            $query->where('status', 'disponible');
        }])->with('product')->get();

        foreach ($variants as $variant) {
            if ($variant->units_count <= 10 && $variant->units_count > 0) {
                $productName = $variant->product->name ?? 'Produit';

                $exists = Notification::where('type', 'low_stock')
                    ->where('title', 'like', "%$variant->sku%")
                    ->where('created_at', '>', Carbon::now()->subDays(3))
                    ->exists();

                if (!$exists) {
                    Notification::create([
                        'user_id' => null, // Global Alert
                        'type' => 'low_stock',
                        'title' => "Stock Faible : $variant->sku",
                        'message' => "Le produit '$productName' (SKU: $variant->sku) n'a plus que {$variant->units_count} unités disponibles.",
                        'action_url' => "/catalog/variants/{$variant->id}"
                    ]);
                }
            }
        }
    }
}
