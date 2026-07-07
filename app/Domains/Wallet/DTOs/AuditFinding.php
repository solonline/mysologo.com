<?php

namespace App\Domains\Wallet\DTOs;

class AuditFinding
{
    public function __construct(
        public readonly string $severity,
        public readonly string $code,
        public readonly string $message,
        /** @var array<string, mixed> */
        public readonly array $context = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
