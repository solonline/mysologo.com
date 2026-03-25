<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDiditSession extends Model
{
    //

    protected $fillable = [
        'customer_id',
        'status',
        'session_id',
        'workflow_id',
        'session_url',
        'callback',
        'metadata',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
