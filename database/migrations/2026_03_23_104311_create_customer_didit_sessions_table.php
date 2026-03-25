<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_didit_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Status da sessão no Didit
            $table->string('status')->nullable();

            // ID da sessão no Didit
            $table->string('session_id')->unique();
            $table->string('workflow_id')->nullable();
            $table->string('session_url')->unique();
            $table->string('callback')->nullable();

            // payload bruto (debug/auditoria)
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_didit_sessions');
    }
};
