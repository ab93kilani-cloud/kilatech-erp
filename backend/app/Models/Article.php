<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image',
        'product_id',
        'status',
        'category',
        'meta_title',
        'meta_description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
