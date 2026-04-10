<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'tpa_id',
        'stripe_payment_method_fee_id',
        'related_transaction_id',
        'type',
        'direction',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'payment_method_type',
        'amount',
        'stripe_fee',
        'platform_fee',
        'net_amount',
        'currency',
        'status',
        'description',
        'stripe_metadata',
        'stripe_created_at',
        'settles_at',
    ];

    protected $casts = [
        'type'              => TransactionType::class,
        'direction'         => TransactionDirection::class,
        'stripe_metadata'   => 'array',
        'stripe_created_at' => 'datetime',
        'settles_at'        => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tpa()
    {
        return $this->belongsTo(Tpa::class);
    }

    public function paymentMethodFee()
    {
        return $this->belongsTo(StripePaymentMethodFee::class, 'stripe_payment_method_fee_id');
    }

    public function relatedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }
}
