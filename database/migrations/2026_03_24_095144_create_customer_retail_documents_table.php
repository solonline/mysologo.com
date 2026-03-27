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
        Schema::create('customer_retail_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            // Se o documento for de responsável de empresa, corporate_id referencia a empresa (customers)
            $table->foreignId('corporate_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('document_type')->default('Identity Card');
            $table->string('document_number');
            $table->string('file_path')->nullable();
            $table->string('personal_number')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('expiration_date')->nullable();
            $table->string('date_of_issue')->nullable();
            $table->string('issuing_state')->nullable();
            $table->string('issuing_state_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->enum('gender', ['M', 'F', 'U'])->nullable();
            $table->string('address')->nullable();
            $table->string('formatted_address')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->enum('marital_status', ['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED', 'UNKNOWN'])->nullable();
            $table->string('nationality')->nullable();

            // Imagens e vídeos
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('full_front_image')->nullable();
            $table->string('full_back_image')->nullable();
            $table->string('portrait_image')->nullable();
            $table->string('front_video')->nullable();
            $table->string('back_video')->nullable();
            $table->string('back_image_camera_front')->nullable();
            $table->string('front_image_camera_front')->nullable();

            // Scores e métricas
            $table->float('face_match_score')->nullable();
            $table->float('liveness_score')->nullable();
            $table->float('back_image_camera_front_face_match_score')->nullable();
            $table->float('front_image_camera_front_face_match_score')->nullable();
            $table->json('front_image_quality_score')->nullable();
            $table->json('back_image_quality_score')->nullable();

            // Campos extras e aninhados
            $table->json('extra_fields')->nullable();
            $table->json('parsed_address')->nullable();

            //ip
            $table->json('ip_analyses')->nullable();

            //nfc verification
            $table->json('nfc_verifications')->nullable();

            //phone verification
            $table->json('phone_verifications')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_retail_documents');
    }
};
