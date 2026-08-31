<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Enums;

enum StripePaymentIntentPurpose: string
{
    case Topup = 'topup';
    case Terminal = 'terminal';
    case BankTransferTopup = 'bank_transfer_topup';
    case MerchantPayment = 'merchant_payment';
}
