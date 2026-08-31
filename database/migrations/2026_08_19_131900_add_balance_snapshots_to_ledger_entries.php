<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->bigInteger('available_before')->nullable()->after('amount');
            $table->bigInteger('available_after')->nullable()->after('available_before');
            $table->bigInteger('pending_before')->nullable()->after('available_after');
            $table->bigInteger('pending_after')->nullable()->after('pending_before');
        });

        $this->backfillSnapshots();
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropColumn([
                'available_before',
                'available_after',
                'pending_before',
                'pending_after',
            ]);
        });
    }

    private function backfillSnapshots(): void
    {
        $groups = DB::table('ledger_entries')
            ->select('customer_id', 'currency')
            ->distinct()
            ->orderBy('customer_id')
            ->get();

        foreach ($groups as $group) {
            $available = 0;
            $pending = 0;

            $entries = DB::table('ledger_entries')
                ->where('customer_id', $group->customer_id)
                ->where('currency', $group->currency)
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                $availableBefore = $available;
                $pendingBefore = $pending;
                $amount = (int) $entry->amount;
                $delta = $entry->direction === 'credit' ? $amount : -$amount;
                $bucket = data_get($this->decodeMetadata($entry->metadata), 'bucket');

                if ($bucket === 'pending') {
                    $pending += $delta;
                } elseif ($entry->entry_type === 'partner_funding_released') {
                    $pending -= $amount;
                    $available += $amount;
                } else {
                    $available += $delta;
                }

                DB::table('ledger_entries')->where('id', $entry->id)->update([
                    'available_before' => $availableBefore,
                    'available_after' => $available,
                    'pending_before' => $pendingBefore,
                    'pending_after' => $pending,
                    'balance_after' => $available,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
};
