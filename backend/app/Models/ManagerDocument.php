<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerDocument extends Model
{
    protected $fillable = [
        'title',
        'type',
        'month',
        'year',
        'amount',
        'file_path',
        'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
