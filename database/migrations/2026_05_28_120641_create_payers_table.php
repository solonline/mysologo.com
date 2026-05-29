<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();

            $table->string('full_name')->nullable();

            // Email é o identificador primário — único, usado como chave de lookup
            $table->string('email_address')->nullable()->unique();

            // Telefone é informativo — não é único porque vários compradores
            // podem partilhar o mesmo número (família, empresa, etc.)
            $table->string('phone_number')->nullable();

            $table->timestamps();

            // Lookup rápido por telefone quando não há email
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
