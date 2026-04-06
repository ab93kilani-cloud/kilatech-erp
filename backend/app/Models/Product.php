<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'name', 'slug', 'category', 'unit', 'supplier_id', 'brand',
        'description', 'meta_title', 'meta_description', 'meta_keywords',
        'social_linkedin_post', 'social_facebook_post', 'social_instagram_post', 'local_geo_context',
        'is_medical', 'cost_price', 
        'price_ht', 'tva_rate', 'public_price', 
        'margin_percent', 'image_path', 'is_showcase'
    ];

    protected $casts = [
        'is_medical' => 'boolean',
        'is_showcase' => 'boolean',
        'cost_price' => 'decimal:4',
        'price_ht' => 'decimal:4',
        'public_price' => 'decimal:4',
        'tva_rate' => 'decimal:2',
        'margin_percent' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }


    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
