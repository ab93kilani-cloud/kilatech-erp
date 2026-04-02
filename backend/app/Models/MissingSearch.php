<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissingSearch extends Model
{
    use HasFactory;

    protected $fillable = ['query', 'count', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
