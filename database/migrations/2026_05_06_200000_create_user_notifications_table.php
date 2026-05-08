<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                      // ex: transaction.created
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();            // payload extra (ids, amounts, etc.)
            $table->string('permission_required')->nullable(); // permissão que originou
            $table->string('url')->nullable();           // link para onde navegar ao clicar
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
