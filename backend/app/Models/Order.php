<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = ['quote_id', 'client_id', 'order_number', 'order_date', 'status', 'discount_amount', 'payment_method'];

    public function client()
    {
        return $this->belongsTo(Client::class);
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
