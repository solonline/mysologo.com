<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'currency',
        'wallester_allocated',
        'pending',
        'wise_allocated',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
