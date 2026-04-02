<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'name_variation', 
        'cost_price', 'price_ht', 'tva_rate', 
        'margin_percent', 'public_price'
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'price_ht' => 'decimal:4',
        'public_price' => 'decimal:4',
        'tva_rate' => 'decimal:2',
        'margin_percent' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function suppliers()
    {
        return $this->hasMany(ProductSupplier::class, 'variant_id');
    }

    public function lots()
    {
        return $this->hasMany(Lot::class, 'variant_id');
    }

    public function units()
    {
        return $this->hasManyThrough(StockUnit::class, Lot::class, 'variant_id', 'lot_id');
    }
}
