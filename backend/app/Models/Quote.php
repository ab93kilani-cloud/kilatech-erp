<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'quote_number', 'client_id', 'version', 'status', 'locked_by_user_id', 'locked_at',
        'valid_until', 'discount_amount', 'payment_method', 'payment_term', 'quote_date'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->quote_number)) {
                $prefix = 'DEV-' . date('ym');
                $lastQuote = static::withTrashed()
                    ->where('quote_number', 'like', $prefix . '-%')
                    ->get()
                    ->sortByDesc(function($q) {
                        $parts = explode('-', $q->quote_number);
                        return (int) end($parts);
                    })
                    ->first();
                if ($lastQuote) {
                    $parts = explode('-', $lastQuote->quote_number);
                    $nextNum = ((int) end($parts)) + 1;
                } else {
                    $nextNum = 1;
                }
                $quote->quote_number = $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lines()
    {
        return $this->hasMany(QuoteLine::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }
}
