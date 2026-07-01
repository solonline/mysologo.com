<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWallesterCard extends Model
{
    protected $fillable = [
        'customer_wallester_account_id',
        'wallester_card_id',
        'type',
        'status',
        'name',
        'masked_card_number',
        'expiry_date',
        'is_disposable',
        'disposable_type',
        'block_type',
        'blocked_at',
        'blocked_by',
        'close_reason',
        'closed_at',
        'closed_by',
        'activated_at',
        'external_id',
        'person_id',
        'embossing_name',
        'embossing_company_name',
        'embossing_first_name',
        'embossing_last_name',
        'personalization_product_code',
        'predecessor_card_id',
        'reference_number',
        'renew_automatically',
        'carrier_type',
        'dispatched_at',
        'delivery_address',
        'security',
        'limits',
        'three_d_secure_settings',
        'notification_settings',
        'card_metadata_profile_id',
        'wallester_created_at',
        'wallester_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'datetime',
            'is_disposable' => 'boolean',
            'blocked_at' => 'datetime',
            'closed_at' => 'datetime',
            'activated_at' => 'datetime',
            'renew_automatically' => 'boolean',
            'dispatched_at' => 'datetime',
            'delivery_address' => 'array',
            'security' => 'array',
            'limits' => 'array',
            'three_d_secure_settings' => 'array',
            'notification_settings' => 'array',
            'wallester_created_at' => 'datetime',
            'wallester_updated_at' => 'datetime',
        ];
    }

    public function wallesterAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerWallesterAccount::class, 'customer_wallester_account_id');
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public static function attributesFromWallesterResponse(array $card, int $customerWallesterAccountId): array
    {
        $threeDSecure = is_array($card['3d_secure_settings'] ?? null)
            ? $card['3d_secure_settings']
            : null;

        if (is_array($threeDSecure)) {
            unset($threeDSecure['password']);
        }

        return [
            'customer_wallester_account_id' => $customerWallesterAccountId,
            'wallester_card_id' => (string) $card['id'],
            'type' => $card['type'] ?? null,
            'status' => $card['status'] ?? null,
            'name' => $card['name'] ?? null,
            'masked_card_number' => $card['masked_card_number'] ?? null,
            'expiry_date' => $card['expiry_date'] ?? null,
            'is_disposable' => (bool) ($card['is_disposable'] ?? false),
            'disposable_type' => $card['disposable_type'] ?? null,
            'block_type' => $card['block_type'] ?? null,
            'blocked_at' => $card['blocked_at'] ?? null,
            'blocked_by' => $card['blocked_by'] ?? null,
            'close_reason' => $card['close_reason'] ?? null,
            'closed_at' => $card['closed_at'] ?? null,
            'closed_by' => $card['closed_by'] ?? null,
            'activated_at' => $card['activated_at'] ?? null,
            'external_id' => $card['external_id'] ?? null,
            'person_id' => $card['person_id'] ?? null,
            'embossing_name' => $card['embossing_name'] ?? null,
            'embossing_company_name' => $card['embossing_company_name'] ?? null,
            'embossing_first_name' => $card['embossing_first_name'] ?? null,
            'embossing_last_name' => $card['embossing_last_name'] ?? null,
            'personalization_product_code' => $card['personalization_product_code'] ?? null,
            'predecessor_card_id' => $card['predecessor_card_id'] ?? null,
            'reference_number' => $card['reference_number'] ?? null,
            'renew_automatically' => (bool) ($card['renew_automatically'] ?? false),
            'carrier_type' => $card['carrier_type'] ?? null,
            'dispatched_at' => $card['dispatched_at'] ?? null,
            'delivery_address' => $card['delivery_address'] ?? null,
            'security' => $card['security'] ?? null,
            'limits' => $card['limits'] ?? null,
            'three_d_secure_settings' => $threeDSecure,
            'notification_settings' => $card['card_notification_settings'] ?? null,
            'card_metadata_profile_id' => $card['card_metadata_profile_id'] ?? null,
            'wallester_created_at' => $card['created_at'] ?? null,
            'wallester_updated_at' => $card['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public static function updatableAttributesFromWallesterResponse(array $card): array
    {
        $attributes = self::attributesFromWallesterResponse($card, 0);

        unset($attributes['customer_wallester_account_id'], $attributes['wallester_card_id']);

        return $attributes;
    }
}
