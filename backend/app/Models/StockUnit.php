<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockUnit extends Model
{
    use SoftDeletes;

    protected $fillable = ['lot_id', 'serial_number', 'status'];

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'unit_id');
    }
}
