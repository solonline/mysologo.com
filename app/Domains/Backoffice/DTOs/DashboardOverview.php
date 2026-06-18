<?php

namespace App\Domains\Backoffice\DTOs;

use Illuminate\Support\Collection;

readonly class DashboardOverview
{
    /**
     * @param  array<string, int|float|bool>  $stats
     * @param  Collection<int, \App\Models\Customer>  $recentCustomers
     * @param  Collection<int, \App\Models\Customer>  $platformCustomers
     */
    public function __construct(
        public array $stats,
        public Collection $recentCustomers,
        public Collection $platformCustomers,
    ) {}
}
