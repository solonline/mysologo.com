<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int         $id
 * @property string|null $full_name
 * @property string|null $email_address
 * @property string|null $phone_number
 */
class Payer extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email_address',
        'phone_number',
    ];

    /**
     * Encontra um payer existente ou cria um novo.
     *
     * Prioridade de lookup:
     *  1. email_address (identificador primário — único)
     *  2. phone_number  (fallback quando não há email)
     *
     * Devolve null se nenhum dado identificador for fornecido.
     */
    public static function findOrCreateFromData(
        ?string $fullName,
        ?string $emailAddress,
        ?string $phoneNumber,
    ): ?self {
        if (! $emailAddress && ! $phoneNumber) {
            return null;
        }

        $payer = null;

        if ($emailAddress) {
            $payer = self::where('email_address', $emailAddress)->first();
        }

        if (! $payer && $phoneNumber) {
            $payer = self::where('phone_number', $phoneNumber)->latest()->first();
        }

        if ($payer) {
            $updates = [];

            if ($fullName && $payer->full_name !== $fullName) {
                $updates['full_name'] = $fullName;
            }
            if ($emailAddress && ! $payer->email_address) {
                $updates['email_address'] = $emailAddress;
            }
            if ($phoneNumber && ! $payer->phone_number) {
                $updates['phone_number'] = $phoneNumber;
            }

            if ($updates) {
                $payer->update($updates);
            }

            return $payer;
        }

        return self::create([
            'full_name'     => $fullName,
            'email_address' => $emailAddress,
            'phone_number'  => $phoneNumber,
        ]);
    }

    public function paymentRequests()
    {
        return $this->hasMany(MerchantPaymentRequest::class);
    }
}
