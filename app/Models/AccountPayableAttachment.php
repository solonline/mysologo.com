<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AccountPayableAttachment extends Model
{
    protected $fillable = [
        'account_payable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}
