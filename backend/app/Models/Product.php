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
        'price_ht', 'tva_rate', 'public_price', 'unit_name', 'packaging_unit', 'sales_multiplier',
        'margin_percent', 'image_path', 'images', 'is_showcase',
        'seo_title', 'seo_description', 'seo_content', 'seo_h1',
        'seo_hash', 'seo_status', 'seo_priority', 'seo_score', 
        'seo_audit', 'gsc_position', 'gsc_ctr', 'gsc_impressions', 'last_seo_update'
    ];

    protected $casts = [
        'is_medical' => 'boolean',
        'is_showcase' => 'boolean',
        'cost_price' => 'decimal:4',
        'price_ht' => 'decimal:4',
        'public_price' => 'decimal:4',
        'tva_rate' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'images' => 'array',
        'seo_audit' => 'array',
        'last_seo_update' => 'datetime',
        'gsc_ctr' => 'decimal:2',
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
