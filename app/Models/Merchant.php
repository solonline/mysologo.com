<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int         $id
 * @property int         $customer_id
 * @property string      $name
 * @property string      $slug
 * @property string      $api_key
 * @property string      $mode
 * @property string|null $webhook_url
 * @property array|null  $allowed_currencies
 * @property bool        $is_active
 */
class Merchant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'slug',
        'api_key',
        'webhook_url',
        'allowed_currencies',
        'mode',
        'is_active',
    ];

    protected $casts = [
        'allowed_currencies' => 'array',
        'is_active'          => 'boolean',
    ];

    protected $hidden = ['api_key'];

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
