<?php

namespace App\Domains\Wallet\Enums;

use App\Models\StripeTransaction;

enum TopupSettlementRoute: string
{
    case WallesterImmediate = 'wallester_immediate';
    case StripeDeferred = 'stripe_deferred';

    public static function resolve(StripeTransaction $transaction): ?self
    {
        if ($transaction->type !== TransactionType::TOPUP) {
            return null;
        }

        if ((int) ($transaction->wallester_funding_net_amount ?? 0) > 0) {
            return self::WallesterImmediate;
        }

        if ((int) ($transaction->stripe_deferred_net_amount ?? 0) > 0) {
            return self::StripeDeferred;
        }

        $fundingStatus = $transaction->partner_funding_status;

        if ($fundingStatus instanceof PartnerFundingStatus
            && $fundingStatus !== PartnerFundingStatus::NotRequired) {
            return self::WallesterImmediate;
        }

        return self::StripeDeferred;
    }

    public function label(): string
    {
        return match ($this) {
            self::WallesterImmediate => 'Wallester imediato',
            self::StripeDeferred => 'Settlement Stripe',
        };
    }

    public function badgeClasses(StripeTransaction $transaction): string
    {
        return match ($this) {
            self::WallesterImmediate => match ($transaction->partner_funding_status) {
                PartnerFundingStatus::Completed => 'bg-violet-50 text-violet-700 border-violet-200',
                PartnerFundingStatus::Failed => 'bg-red-50 text-red-700 border-red-200',
                PartnerFundingStatus::AwaitingLiquidity, PartnerFundingStatus::Pending => 'bg-amber-50 text-amber-700 border-amber-200',
                default => 'bg-violet-50 text-violet-700 border-violet-200',
            },
            self::StripeDeferred => $transaction->balance_settled_at !== null
                ? 'bg-slate-100 text-slate-600 border-slate-200'
                : 'bg-sky-50 text-sky-700 border-sky-200',
        };
    }

    public function detail(StripeTransaction $transaction): ?string
    {
        return match ($this) {
            self::WallesterImmediate => match ($transaction->partner_funding_status) {
                PartnerFundingStatus::Completed => $transaction->partner_funded_at
                    ? 'Fundado '.$transaction->partner_funded_at->format('d/m/Y H:i')
                    : 'Fundado na Wallester',
                PartnerFundingStatus::Pending => 'Transferência pendente',
                PartnerFundingStatus::AwaitingLiquidity => 'Aguarda liquidez na main',
                PartnerFundingStatus::Failed => $transaction->partner_funding_failure_reason
                    ? \Illuminate\Support\Str::limit($transaction->partner_funding_failure_reason, 60)
                    : 'Falha no funding Wallester',
                default => null,
            },
            self::StripeDeferred => $this->stripeDeferredDetail($transaction),
        };
    }

    public function showAvailableOn(StripeTransaction $transaction): bool
    {
        return $this === self::StripeDeferred
            && $transaction->available_on !== null;
    }

    private function stripeDeferredDetail(StripeTransaction $transaction): ?string
    {
        if ($transaction->balance_settled_at !== null) {
            return 'Liquidado '.$transaction->balance_settled_at->format('d/m/Y');
        }

        if ($transaction->available_on === null) {
            return 'Aguarda settlement Stripe';
        }

        $available = $transaction->available_on->isPast()
            || $transaction->available_on->isToday();

        return ($available ? 'Disp. desde ' : 'Disp. em ')
            .$transaction->available_on->format('d/m/Y');
    }
}
