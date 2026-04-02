<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSupplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['variant_id', 'supplier_id', 'supplier_ref', 'purchase_price', 'lead_time_days', 'price_validity_date'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
