<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payables', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier');
            $table->string('description');
            $table->string('category')->nullable();
            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('pending');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_interval', 20)->nullable();
            $table->date('recurrence_ends_at')->nullable();
            $table->unsignedInteger('recurrence_occurrences')->nullable();
            $table->uuid('recurrence_group_uuid')->nullable()->index();
            $table->unsignedInteger('occurrence_index')->default(1);
            $table->unsignedInteger('occurrence_total')->default(1);
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['supplier', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
