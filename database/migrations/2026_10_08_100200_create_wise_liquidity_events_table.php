<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wise_liquidity_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('delivery_id')->nullable()->unique();
            $table->unsignedBigInteger('balance_id')->nullable();
            $table->char('currency', 3)->nullable();
            $table->bigInteger('amount_cents')->default(0);
            $table->decimal('post_transaction_balance', 16, 4)->nullable();
            $table->string('channel_name')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['currency', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wise_liquidity_events');
    }
};
