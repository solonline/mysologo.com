<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'phone',
        'code',
        'verified_at',
    ];

    protected $dates = [
        'verified_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
