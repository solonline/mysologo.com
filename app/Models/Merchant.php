<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int         $id
 * @property int         $customer_id
 * @property string      $name
 * @property string      $slug
 * @property string      $api_key
 * @property string|null $webhook_secret
 * @property string      $mode
 * @property string|null $webhook_url
 * @property array|null  $allowed_currencies
 * @property bool        $is_active
 */
class Merchant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['api_key', 'webhook_secret'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('merchants');
    }

    protected $fillable = [
        'customer_id',
        'name',
        'slug',
        'api_key',
        'webhook_secret',
        'webhook_url',
        'allowed_currencies',
        'mode',
        'is_active',
    ];

    protected $casts = [
        'allowed_currencies' => 'array',
        'is_active'          => 'boolean',
        'webhook_secret'     => 'encrypted',
    ];

    protected $hidden = ['api_key', 'webhook_secret'];

    public static function findByRawKey(string $rawKey): ?self
    {
        return self::where('api_key', hash('sha256', $rawKey))
            ->where('is_active', true)
            ->first();
    }

    public function allowsCurrency(string $currency): bool
    {
        if (empty($this->allowed_currencies)) {
            return true;
        }

        return \in_array(strtoupper($currency), $this->allowed_currencies, strict: true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentRequests()
    {
        return $this->hasMany(MerchantPaymentRequest::class);
    }
}
