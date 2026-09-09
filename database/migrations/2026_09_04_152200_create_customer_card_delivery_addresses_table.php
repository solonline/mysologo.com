<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_card_delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_wallester_card_id');
            $table->unique('customer_wallester_card_id', 'card_delivery_addr_card_uidx');
            $table->foreign('customer_wallester_card_id', 'card_delivery_addr_card_fk')
                ->references('id')
                ->on('customer_wallester_cards')
                ->cascadeOnDelete();
            $table->string('address');
            $table->string('city');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('postal_code');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_card_delivery_addresses');
    }
};
