<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;
use Illuminate\Support\Facades\DB;

class CreateAccountPayableAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<AccountPayable>
     */
    public function execute(array $data): array
    {
        return DB::transaction(fn () => AccountPayable::createSeries($data));
    }
}
