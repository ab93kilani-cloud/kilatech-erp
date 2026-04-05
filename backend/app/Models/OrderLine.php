<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLine extends Model
{
    use SoftDeletes;

    protected $fillable = ['order_id', 'variant_id', 'client_product_ref', 'lot_number', 'serial_numbers', 'expiry_date', 'unit_price', 'quantity', 'discount_percent', 'tax_rate'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
