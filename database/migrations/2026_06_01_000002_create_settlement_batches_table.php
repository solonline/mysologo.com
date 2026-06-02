<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();

            $table->char('currency', 3);
            $table->date('available_date');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('transaction_count')->default(0);

            // Etapa 1: transferência agregada para conta master
            $table->string('master_transfer_id')->nullable()->index();
            $table->timestamp('master_transferred_at')->nullable();

            // Etapa 2: distribuição concluída
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index(['status', 'available_date']);
            $table->unique(['currency', 'available_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_batches');
    }
};
