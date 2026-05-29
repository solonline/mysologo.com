<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tpa extends Model
{
    protected $fillable = [
        'customer_id',
        'serial',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethodFees()
    {
        return $this->hasMany(CustomerPaymentMethodFee::class);
    }
}
