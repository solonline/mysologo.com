<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDevice extends Model
{
    protected $fillable = [
        'customer_id',
        'device_name',
        'device_token',
        'pin',
        'blocked_at',
        'failed_attempts',
        'last_used_at',
        'push_token',
        'platform',
        'push_token_seen_at',
        'push_enabled',
    ];

    protected $attributes = [
        'push_enabled' => false,
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'push_token_seen_at' => 'datetime',
        'push_enabled' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
