<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCorporateDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legal_name',
        'trading_name',
        'country_code',
        'tax_id',
        'document_type',
        'vat_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'email',
        'phone_international',
        'website',
        'currency_code',
        'extra_attributes',
    ];

    protected $casts = [
        'extra_attributes' => 'array',
    ];

    public function responsibleDocuments()
    {
        return $this->hasMany(CustomerRetailDocument::class, 'company_id');
    }
}
