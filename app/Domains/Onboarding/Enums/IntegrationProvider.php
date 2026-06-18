<?php

namespace App\Domains\Onboarding\Enums;

enum IntegrationProvider: string
{
    case STRIPE = 'stripe';
    case WALLESTER = 'wallester';
    case EBURY = 'ebury';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::WALLESTER => 'Wallester',
            self::EBURY => 'Ebury',
        };
    }
}
