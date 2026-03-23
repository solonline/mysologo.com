<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('username', 40)->unique();
            $table->string('avatar')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('referal_email_code')->unique()->nullable();
            $table->string('didit_user_id')->nullable()->index();
            $table->enum('doc_status', ['pending', 'submitted', 'analysing', 'approved', 'rejected', 'retry'])->default('pending');
            $table->string('phone', 20)->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
