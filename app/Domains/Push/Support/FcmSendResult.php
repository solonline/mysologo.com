<?php

namespace App\Domains\Push\Support;

readonly class FcmSendResult
{
    public function __construct(
        public bool $sent,
        public bool $invalidToken = false,
    ) {}
}
