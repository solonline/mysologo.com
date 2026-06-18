<?php

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ebury_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('ebury_account_id')->unique();
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 20)->default(IntegrationAccountStatus::PENDING->value);
            $table->string('iban')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ebury_accounts');
    }
};
