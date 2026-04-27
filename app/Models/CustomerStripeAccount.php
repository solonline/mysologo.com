<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerStripeAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'currency',
        'status',
        'viban'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
