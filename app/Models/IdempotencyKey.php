<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
     protected $fillable = [
        'key',
        'endpoint',
        'request_hash',
        'response',
        'status_code',
    ];
}
