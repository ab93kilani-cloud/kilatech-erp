<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteLine extends Model
{
    use SoftDeletes;

    protected $fillable = ['quote_id', 'variant_id', 'client_product_ref', 'unit_price', 'quantity', 'discount_percent', 'tax_rate'];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
