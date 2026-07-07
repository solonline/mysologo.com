<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funding_partner_movements')) {
            return;
        }

        Schema::create('funding_partner_movements', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('funding_account_type');
            $table->unsignedBigInteger('funding_account_id');
            $table->index(['funding_account_type', 'funding_account_id'], 'fpm_funding_account_idx');
            $table->string('external_account_id');
            $table->string('direction');
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->uuid('transfer_group_id')->nullable();
            $table->string('counterparty_external_account_id')->nullable();
            $table->foreignId('customer_wallester_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_external_id')->nullable()->unique();
            $table->string('status');
            $table->string('source');
            $table->string('idempotency_key')->unique();
            $table->text('failure_reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id'], 'fpm_reference_idx');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider', 'external_account_id']);
            $table->index('transfer_group_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_partner_movements');
    }
};
