<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lot extends Model
{
    use SoftDeletes;

    protected $fillable = ['variant_id', 'lot_number', 'expiration_date', 'actual_purchase_price'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function units()
    {
        return $this->hasMany(StockUnit::class);
    }
}
