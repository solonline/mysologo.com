<?php

declare(strict_types=1);

namespace App\Domains\Stripe\Enums;

enum StripePaymentIntentStatus: string
{
    case RequiresPaymentMethod = 'requires_payment_method';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case RequiresCapture = 'requires_capture';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';
    case Failed = 'failed';

    public static function fromStripe(?string $status): self
    {
        $status = $status === 'cancelled' ? 'canceled' : (string) $status;

        return self::tryFrom($status) ?? self::RequiresPaymentMethod;
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::RequiresPaymentMethod,
            self::RequiresConfirmation,
            self::RequiresAction,
            self::Processing,
            self::RequiresCapture,
        ], true);
    }

    public function isCapturable(): bool
    {
        return in_array($this, [
            self::RequiresCapture,
            self::RequiresConfirmation,
            self::Processing,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Canceled,
            self::Failed,
        ], true);
    }
}
