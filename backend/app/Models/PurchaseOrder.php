<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = ['supplier_id', 'created_by', 'po_number', 'po_date', 'status', 'payment_method', 'payment_conditions', 'expected_delivery_date'];

    protected $appends = ['total_ttc'];
    
    public function getTotalTtcAttribute()
    {
        return $this->lines->sum(function($line) {
            $price = $line->unit_price;
            // Fallback to variant cost price if unit_price is 0/null
            if (!$price && $line->variant) {
                $price = $line->variant->cost_price;
            }
            $ht = (float)($price ?? 0) * (int)($line->ordered_quantity ?? 0);
            $tvaRate = (float)($line->variant?->tva_rate ?? 20);
            return $ht * (1 + ($tvaRate / 100));
        });
    }

    public function lines()
    {
        return $this->hasMany(PoLine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
