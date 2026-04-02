<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = ['type', 'company_name', 'contact_name', 'email', 'phone', 'telephone_fix', 'fax', 'address', 'city', 'ice', 'rc', 'if', 'patente', 'status'];

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
