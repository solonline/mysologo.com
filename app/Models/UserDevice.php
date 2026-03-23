<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'last_used_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
