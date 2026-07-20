<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use Carbon\Carbon;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
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

    public function stripeTransactions()
    {
        return $this->hasMany(StripeTransaction::class);
    }

    public function stripeAccount()
    {
        return $this->hasOne(CustomerStripeAccount::class);
    }

    public function wallesterAccounts(): HasMany
    {
        return $this->hasMany(CustomerWallesterAccount::class);
    }

    public function wallesterAccount(): HasOne
    {
        return $this->hasOne(CustomerWallesterAccount::class)
            ->where('is_main', false)
            ->latestOfMany();
    }

    public function platformWallesterMainAccounts(): HasMany
    {
        return $this->hasMany(CustomerWallesterAccount::class)
            ->where('is_main', true);
    }

    public function wallesterAccountFor(string $currency): ?CustomerWallesterAccount
    {
        $routingCurrency = app(PlatformMasterCurrencyResolver::class)->resolve($currency);

        return $this->wallesterAccounts()
            ->where('funding_currency', $routingCurrency)
            ->first();
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

    public function balanceAdjustments(): HasMany
    {
        return $this->hasMany(CustomerBalanceAdjustment::class);
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
