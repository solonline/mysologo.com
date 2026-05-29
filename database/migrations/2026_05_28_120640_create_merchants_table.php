<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            // SHA-256 da chave raw — nunca guardamos a chave em plain text
            $table->string('api_key', 64)->unique();

            $table->string('webhook_url')->nullable();
            $table->json('allowed_currencies')->nullable();

            $table->enum('mode', ['test', 'live'])->default('test');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
