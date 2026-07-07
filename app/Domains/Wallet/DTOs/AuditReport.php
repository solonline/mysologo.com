<?php

namespace App\Domains\Wallet\DTOs;

class AuditReport
{
    /** @param  list<AuditFinding>  $findings */
    public function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?int $customerId,
        public readonly ?string $currency,
        public readonly array $findings,
    ) {}

    /** @return array{critical: int, warning: int, info: int} */
    public function countsBySeverity(): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];

        foreach ($this->findings as $finding) {
            if (isset($counts[$finding->severity])) {
                $counts[$finding->severity]++;
            }
        }

        return $counts;
    }

    public function hasCritical(): bool
    {
        return $this->countsBySeverity()['critical'] > 0;
    }
}
