<?php

namespace Database\Seeders;

use App\Models\StripePaymentMethodFee;
use Illuminate\Database\Seeder;

class StripePaymentMethodFeeSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'payment_method'          => 'card',
                'label'                   => 'Cartão (EEA)',
                'stripe_fee_percentage'   => 0.0150, // 1.5%
                'stripe_fee_fixed'        => 0.25,   // €0.25
                'platform_fee_percentage' => 0.0050, // 0.5%
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
            [
                'payment_method'          => 'card_non_eea',
                'label'                   => 'Cartão (Não-EEA)',
                'stripe_fee_percentage'   => 0.0325, // 3.25%
                'stripe_fee_fixed'        => 0.25,   // €0.25
                'platform_fee_percentage' => 0.0050,
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
            [
                'payment_method'          => 'sepa_debit',
                'label'                   => 'SEPA Direct Debit',
                'stripe_fee_percentage'   => 0.0036, // 0.36%
                'stripe_fee_fixed'        => 0.00,
                'platform_fee_percentage' => 0.0025,
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
            [
                'payment_method'          => 'mb_way',
                'label'                   => 'MB WAY',
                'stripe_fee_percentage'   => 0.0100, // 1.0%
                'stripe_fee_fixed'        => 0.00,
                'platform_fee_percentage' => 0.0050,
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
            [
                'payment_method'          => 'multibanco',
                'label'                   => 'Multibanco',
                'stripe_fee_percentage'   => 0.0100, // 1.0%
                'stripe_fee_fixed'        => 0.00,
                'platform_fee_percentage' => 0.0050,
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
            [
                'payment_method'          => 'bank_transfer',
                'label'                   => 'Transferência Bancária',
                'stripe_fee_percentage'   => 0.0000,
                'stripe_fee_fixed'        => 0.00,
                'platform_fee_percentage' => 0.0025,
                'platform_fee_fixed'      => 0.00,
                'currency'                => 'EUR',
                'is_active'               => true,
            ],
        ];

        foreach ($methods as $method) {
            StripePaymentMethodFee::updateOrCreate(
                ['payment_method' => $method['payment_method']],
                $method
            );
        }
    }
}
