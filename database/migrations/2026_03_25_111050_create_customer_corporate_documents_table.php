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
        Schema::create('customer_corporate_documents', function (Blueprint $table) {
            $table->id();

            // Dados Básicos
            $table->string('legal_name'); // Razão Social
            $table->string('trading_name')->nullable(); // Nome Fantasia
            $table->enum('status', ['Valid', 'Inactive', 'Pending'])->nullable(); // Valido, Inativo, etc
            $table->string('motive')->nullable(); // Motivo de status pendente ou rejeitado
            // Identificação Internacional (O "Coração" da tabela)
            $table->string('country_code', 2); // ISO 3166-1 alpha-2 (BR, PT, ES, GB, FR)
            $table->string('tax_id')->unique(); // O número do doc (CNPJ, NIF, CIF, etc)
            $table->string('document_type')->nullable(); // Nome do doc no país (ex: "CNPJ", "VAT", "SIRET")
            $table->string('vat_number')->nullable()->unique(); // Comum na Europa (IVA)

            // Endereço Flexível
            $table->string('address_line_1')->nullable(); // Rua, número
            $table->string('address_line_2')->nullable(); // Bairro, complemento
            $table->string('city')->nullable();
            $table->string('state_province')->nullable(); // Estado, Distrito ou Província
            $table->string('postal_code')->nullable(); // String pela variação de formatos (ex: SW1A 1AA)

            // Contato e Configurações
            $table->string('email')->nullable();
            $table->string('phone_international')->nullable(); // Deve conter DDI +55, +351...
            $table->string('website')->nullable();
            $table->char('currency_code', 3)->default('EUR'); // BRL, EUR, GBP

            // Metadados para campos muito específicos de um país
            $table->json('extra_attributes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index para buscas rápidas por país e documento
            $table->index(['country_code', 'tax_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_corporate_documents');
    }
};
