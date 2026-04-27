<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_wallester_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('wallester_account_id')->unique();
            $table->string('currency_code')->unique();
            $table->string('product_id')->unique();
            $table->string('company_id')->unique();
            $table->integer('credit_limit')->nullable();
            $table->integer('used_credit')->nullable();
            $table->integer('balance')->nullable();
            $table->integer('available_amount')->nullable();
            $table->integer('blocked_amount')->nullable();
            $table->integer('blocked_credit_amount')->nullable();
            $table->string('status')->default('active');

            // Novos campos (limits detalhado)
            $table->integer('daily_purchase')->nullable();
            $table->integer('daily_withdrawal')->nullable();
            $table->integer('daily_internet_purchase')->nullable();
            $table->integer('daily_contactless_purchase')->nullable();
            $table->integer('weekly_purchase')->nullable();
            $table->integer('weekly_withdrawal')->nullable();
            $table->integer('weekly_internet_purchase')->nullable();
            $table->integer('weekly_contactless_purchase')->nullable();
            $table->integer('monthly_purchase')->nullable();
            $table->integer('monthly_withdrawal')->nullable();
            $table->integer('monthly_internet_purchase')->nullable();
            $table->integer('monthly_contactless_purchase')->nullable();

            $table->boolean('is_main')->default(false);
            $table->integer('cards_count')->default(0);

            // top_up_details detalhado
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('payment_details')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('registration_number')->nullable();

            $table->string('reference_number')->nullable();

            $table->string('viban')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_wallester_accounts');
    }
};
