<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptLine extends Model
{
    use SoftDeletes;

    protected $fillable = ['receipt_id', 'po_line_id', 'received_quantity', 'status', 'actual_purchase_price', 'lot_number', 'expiry_date'];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function poLine()
    {
        return $this->belongsTo(PoLine::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference_doc');
    }
}
