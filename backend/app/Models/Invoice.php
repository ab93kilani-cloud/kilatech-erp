<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['order_id', 'invoice_number', 'invoice_date', 'total_amount', 'balance_due', 'status', 'discount_amount', 'payment_method'];
    
    protected $casts = [
        'total_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
