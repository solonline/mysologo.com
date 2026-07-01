<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallester_3ds_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('wallester_request_id')->unique();
            $table->uuid('wallester_x_request_id');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_wallester_card_id')->nullable()
                ->constrained('customer_wallester_cards')->nullOnDelete();
            $table->string('wallester_card_id');
            $table->string('status')->default('pending');
            $table->json('card')->nullable();
            $table->json('merchant')->nullable();
            $table->json('transaction')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('wallester_x_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallester_3ds_challenges');
    }
};
