<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rail_mirror_events', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('direction', 16);
            $table->bigInteger('amount');
            $table->string('reason', 64);
            $table->nullableMorphs('reference');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_mirror_events');
    }
};
