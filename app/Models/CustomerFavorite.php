<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFavorite extends Model
{
    protected $fillable = [
        'customer_id',
        'favorite_customer_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function favoriteCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'favorite_customer_id');
    }
}
