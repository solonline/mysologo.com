<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory, Notifiable, MustVerifyEmail;

    protected $fillable = [
        'name',
        'username',
        'type',
        'corporate_id',
        'avatar',
        'language',
        'email',
        'email_verified_at',
        'referal_email_code',
        'didit_user_id',
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
        return $this->hasMany(CustomerCorporateDocument::class, 'id', 'customer_id');
    }

    public function diditSessions()
    {
        return $this->hasMany(CustomerDiditSession::class);
    }
}
