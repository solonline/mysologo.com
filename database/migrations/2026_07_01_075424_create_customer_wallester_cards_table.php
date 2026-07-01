<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallester_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_wallester_account_id')
                ->constrained('customer_wallester_accounts')
                ->cascadeOnDelete();
            $table->string('wallester_card_id')->unique();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('name')->nullable();
            $table->string('masked_card_number')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->boolean('is_disposable')->default(false);
            $table->string('disposable_type')->nullable();
            $table->string('block_type')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_by')->nullable();
            $table->string('close_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->string('external_id')->nullable();
            $table->string('person_id')->nullable();
            $table->string('embossing_name')->nullable();
            $table->string('embossing_company_name')->nullable();
            $table->string('embossing_first_name')->nullable();
            $table->string('embossing_last_name')->nullable();
            $table->string('personalization_product_code')->nullable();
            $table->string('predecessor_card_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('renew_automatically')->default(false);
            $table->string('carrier_type')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->json('delivery_address')->nullable();
            $table->json('security')->nullable();
            $table->json('limits')->nullable();
            $table->json('three_d_secure_settings')->nullable();
            $table->json('notification_settings')->nullable();
            $table->string('card_metadata_profile_id')->nullable();
            $table->timestamp('wallester_created_at')->nullable();
            $table->timestamp('wallester_updated_at')->nullable();
            $table->timestamps();

            $table->index(['customer_wallester_account_id', 'status'], 'wallester_cards_account_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallester_cards');
    }
};
