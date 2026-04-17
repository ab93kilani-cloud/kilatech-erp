<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $fillable = ['invoice_id', 'amount', 'payment_date', 'payment_method', 'reference', 'notes'];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }
}
