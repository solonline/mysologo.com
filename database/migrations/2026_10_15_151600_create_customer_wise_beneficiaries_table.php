<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wise_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('wise_recipient_id');
            $table->string('wise_profile_id');
            $table->string('currency', 3);
            $table->string('type', 50);
            $table->string('account_holder_name');
            $table->boolean('owned_by_customer');
            $table->json('details');
            $table->json('ultimate_beneficiary')->nullable();
            $table->string('wise_hash')->nullable();
            $table->string('account_summary')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'wise_recipient_id'], 'customer_wise_beneficiaries_owner_unique');
            $table->index('wise_recipient_id', 'customer_wise_beneficiaries_recipient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wise_beneficiaries');
    }
};
