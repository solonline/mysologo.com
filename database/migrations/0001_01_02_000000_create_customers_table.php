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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('username', 40)->unique();
            $table->enum('type', ['retail', 'corporate', 'responsible'])->default('retail');
            // Se for responsável, corporate_id referencia a empresa (auto-relacionamento)
            $table->foreignId('corporate_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('avatar')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('referal_email_code')->unique()->nullable();
            $table->enum('status', ['pending', 'analysing', 'active', 'inactive', 'blocked'])->default('pending');
            $table->enum('doc_status', ['approved', 'declined', 'analysing', 'pending'])->default('pending');
            $table->string('phone', 20)->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
