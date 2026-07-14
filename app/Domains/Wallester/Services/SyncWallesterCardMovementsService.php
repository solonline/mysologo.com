<?php

namespace App\Domains\Wallester\Services;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Models\CustomerWallesterCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWallesterCardMovementsService
{
    private const PAGE_SIZE = 100;

    private const DEFAULT_LOOKBACK_HOURS = 13;

    public function __construct(
        protected WallesterGatewayInterface $gateway,
        protected WallesterCardMovementIngestService $cardMovements,
    ) {}

    /**
     * @return array{cards_scanned: int, transactions_fetched: int, failures: int}
     */
    public function run(
        ?int $cardId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $lookbackHours = self::DEFAULT_LOOKBACK_HOURS,
    ): array {
        $to ??= now()->utc();
        $from ??= $to->copy()->subHours($lookbackHours);

        $fromDate = $from->format('Y-m-d\TH:i:s\Z');
        $toDate = $to->format('Y-m-d\TH:i:s\Z');

        $stats = [
            'cards_scanned' => 0,
            'transactions_fetched' => 0,
            'failures' => 0,
        ];

        $query = CustomerWallesterCard::query()
            ->operational()
            ->whereHas('wallesterAccount', fn ($builder) => $builder->where('status', IntegrationAccountStatus::ACTIVE))
            ->with('wallesterAccount')
            ->orderBy('id');

        if ($cardId !== null) {
            $query->whereKey($cardId);
        }

        $query->each(function (CustomerWallesterCard $card) use ($fromDate, $toDate, &$stats): void {
            $stats['cards_scanned']++;

            $account = $card->wallesterAccount;

            if ($account === null) {
                return;
            }

            $currency = (string) ($account->funding_currency ?? 'EUR');
            $auditUserId = (string) $account->customer_id;
            $fromRecord = 0;
            $totalRecords = null;

            do {
                $response = $this->gateway
                    ->forCurrency($currency)
                    ->listCardTransactions($card->wallester_card_id, [
                        'from_record' => $fromRecord,
                        'records_count' => self::PAGE_SIZE,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                    ], $auditUserId);

                $transactions = $response['transactions'];
                $totalRecords ??= $response['total_records_number'];

                if ($transactions === []) {
                    break;
                }

                foreach ($transactions as $transaction) {
                    $stats['transactions_fetched']++;

                    try {
                        $this->cardMovements->ingest($transaction, FundingPartnerMovementSource::Sync);
                    } catch (\Throwable $exception) {
                        $stats['failures']++;

                        Log::channel('wallester')->warning('wallester_card_movement_sync_failed', [
                            'card_id' => $card->id,
                            'wallester_card_id' => $card->wallester_card_id,
                            'transaction_id' => $transaction['id'] ?? null,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                $fromRecord += count($transactions);
            } while ($fromRecord < ($totalRecords ?? 0));
        });

        return $stats;
    }
}
