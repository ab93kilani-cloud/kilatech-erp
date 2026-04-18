<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = ['quote_id', 'order_number', 'client_id', 'order_date', 'due_date', 'status', 'discount_amount', 'payment_method'];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function lines()
    {
        return $this->hasMany(OrderLine::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
