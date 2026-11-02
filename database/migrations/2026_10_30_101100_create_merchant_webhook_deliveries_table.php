<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_webhook_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('merchant_payment_request_id')->nullable()->constrained('merchant_payment_requests')->nullOnDelete();

            $table->uuid('delivery_id')->unique();
            $table->string('event_id');
            $table->string('event_type')->index();
            $table->string('destination_url');

            $table->json('request_body');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();

            $table->unsignedTinyInteger('attempt');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['merchant_id', 'sent_at']);
            $table->index('merchant_payment_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_webhook_deliveries');
    }
};
