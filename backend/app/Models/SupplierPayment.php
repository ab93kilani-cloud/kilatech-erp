<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'notes',
        'maturity_date',
        'cashed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'payment_date' => 'date',
        'maturity_date' => 'date',
        'cashed_at' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }
}
