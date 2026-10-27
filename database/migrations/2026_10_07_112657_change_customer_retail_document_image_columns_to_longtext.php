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
        Schema::table('customer_retail_documents', function (Blueprint $table) {
            $table->longText('front_image')->nullable()->change();
            $table->longText('back_image')->nullable()->change();
            $table->longText('full_front_image')->nullable()->change();
            $table->longText('full_back_image')->nullable()->change();
            $table->longText('portrait_image')->nullable()->change();
            $table->longText('front_video')->nullable()->change();
            $table->longText('back_video')->nullable()->change();
            $table->longText('back_image_camera_front')->nullable()->change();
            $table->longText('front_image_camera_front')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_retail_documents', function (Blueprint $table) {
            $table->string('front_image')->nullable()->change();
            $table->string('back_image')->nullable()->change();
            $table->string('full_front_image')->nullable()->change();
            $table->string('full_back_image')->nullable()->change();
            $table->string('portrait_image')->nullable()->change();
            $table->string('front_video')->nullable()->change();
            $table->string('back_video')->nullable()->change();
            $table->string('back_image_camera_front')->nullable()->change();
            $table->string('front_image_camera_front')->nullable()->change();
        });
    }
};
