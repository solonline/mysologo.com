<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVerification extends Model
{
    protected $fillable = [
        'user_id',
        'didit_transaction_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
