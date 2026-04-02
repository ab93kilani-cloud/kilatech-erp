<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoLine extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['purchase_order_id', 'variant_id', 'unit_price', 'ordered_quantity', 'received_quantity'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
