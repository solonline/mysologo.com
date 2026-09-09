<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCardDeliveryAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_wallester_card_id',
        'address',
        'city',
        'state',
        'country',
        'first_name',
        'last_name',
        'phone',
        'postal_code',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CustomerWallesterCard::class, 'customer_wallester_card_id');
    }
}
