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
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
