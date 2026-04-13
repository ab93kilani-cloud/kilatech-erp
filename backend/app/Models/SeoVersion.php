<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeoVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'seo_title', 'seo_description', 'seo_content', 
        'seo_h1', 'seo_hash', 'seo_score', 'seo_audit'
    ];

    protected $casts = [
        'seo_audit' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
