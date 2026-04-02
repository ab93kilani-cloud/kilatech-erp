<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    // Pas de SoftDeletes ici pour garantir l'historique financier et logistique inaltérable.
    
    protected $fillable = [
        'unit_id', 'user_id', 'type', 'from_status', 'to_status', 
        'reference_doc_type', 'reference_doc_id'
    ];

    public function unit()
    {
        return $this->belongsTo(StockUnit::class, 'unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->morphTo(__FUNCTION__, 'reference_doc_type', 'reference_doc_id');
    }
}
