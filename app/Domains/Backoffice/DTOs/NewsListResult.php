<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class NewsListResult
{
    public function __construct(
        public LengthAwarePaginator $items,
    ) {}
}
