<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_partner_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('original_amount')->nullable()->after('currency');
            $table->string('original_currency', 3)->nullable()->after('original_amount');
            $table->unsignedBigInteger('fees_amount')->nullable()->after('original_currency');
        });

        DB::table('funding_partner_movements')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = is_string($row->metadata)
                        ? json_decode($row->metadata, true)
                        : $row->metadata;

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $updates = [];
                    $source = is_array($metadata['settlement_metadata'] ?? null)
                        ? array_merge($metadata, $metadata['settlement_metadata'])
                        : $metadata;

                    $originalAmount = $source['transaction_amount'] ?? null;
                    $originalCurrency = $source['transaction_currency_code'] ?? null;
                    $feesAmount = $source['fees_amount'] ?? null;

                    if ($originalAmount !== null && $originalAmount !== '') {
                        $cents = abs((int) round(((float) $originalAmount) * 100));
                        if ($cents > 0) {
                            $updates['original_amount'] = $cents;
                        }
                    }

                    if (is_string($originalCurrency) && $originalCurrency !== '') {
                        $updates['original_currency'] = strtoupper($originalCurrency);
                    }

                    if ($feesAmount !== null && $feesAmount !== '') {
                        $cents = abs((int) round(((float) $feesAmount) * 100));
                        if ($cents > 0) {
                            $updates['fees_amount'] = $cents;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('funding_partner_movements')
                            ->where('id', $row->id)
                            ->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('funding_partner_movements', function (Blueprint $table) {
            $table->dropColumn(['original_amount', 'original_currency', 'fees_amount']);
        });
    }
};
