<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class WallesterTransferErrorClassifier
{
    public function classify(Throwable $exception): PartnerFundingStatus
    {
        if ($this->isInsufficientMainLiquidity($exception)) {
            return PartnerFundingStatus::AwaitingLiquidity;
        }

        return PartnerFundingStatus::Failed;
    }

    public function isInsufficientMainLiquidity(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'not enough funds')
            || str_contains($message, 'error_code":20005')
            || str_contains($message, 'error_code\":20005')) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $body = (string) ($exception->getResponse()?->getBody() ?? '');

            if (str_contains(strtolower($body), 'not enough funds')
                || str_contains($body, '"error_code":20005')
                || str_contains($body, '"error_code": 20005')) {
                return true;
            }
        }

        return false;
    }
}
