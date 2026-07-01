<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use Carbon\Carbon;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $currency
 * @property string|null $avatar
 * @property string $type
 * @property string $status
 * @property string|null $doc_status
 * @property Carbon|null $email_verified_at
 * @property string|null $wallester_oob_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Customer extends Model
{
    use HasApiTokens, HasFactory, LogsActivity, MustVerifyEmail, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customers');
    }

    protected $fillable = [
        'wallester_oob_user_id',
        'name',
        'username',
        'type',
        'corporate_id',
        'avatar',
        'language',
        'currency',
        'email',
        'email_verified_at',
        'referal_email_code',
        'didit_user_id',
        'status',
        'status_reason',
        'doc_status',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => CustomerLifecycleStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if ($customer->wallester_oob_user_id === null) {
                $customer->wallester_oob_user_id = (string) Str::uuid();
            }
        });
    }

    // Empresa à qual o responsável pertence
    public function corporate()
    {
        return $this->belongsTo(Customer::class, 'corporate_id');
    }

    // Responsáveis vinculados a esta empresa
    public function responsibles()
    {
        return $this->hasMany(Customer::class, 'corporate_id');
    }

    public function documents()
    {
        return $this->hasMany(CustomerRetailDocument::class);
    }

    // Se quiser acessar empresas (CustomerCorporateDocument) relacionadas a este cliente
    public function corporateDocuments()
    {
        return $this->hasMany(CustomerCorporateDocument::class, 'customer_id', 'id');
    }

    public function diditSessions()
    {
        return $this->hasMany(CustomerDiditSession::class);
    }

    public function tpas()
    {
        return $this->hasMany(Tpa::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function stripeAccount()
    {
        return $this->hasOne(CustomerStripeAccount::class);
    }

    public function wallesterAccounts()
    {
        return $this->hasOne(CustomerWallesterAccount::class);
    }

    public function wallesterAccount()
    {
        return $this->wallesterAccounts();
    }

    public function wallester3dsChallenges(): HasMany
    {
        return $this->hasMany(Wallester3dsChallenge::class);
    }

    public function eburyAccount()
    {
        return $this->hasOne(CustomerEburyAccount::class);
    }

    public function devices()
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function phoneVerifications()
    {
        return $this->hasMany(PhoneVerification::class)->orderBy('created_at', 'desc');
    }

    public function balances()
    {
        return $this->hasMany(CustomerBalance::class);
    }

    public function paymentMethodFees()
    {
        return $this->hasMany(CustomerPaymentMethodFee::class);
    }

    public function merchants()
    {
        return $this->hasMany(Merchant::class);
    }
}
