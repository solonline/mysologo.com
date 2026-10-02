<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_programs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->timestamp('valid_from')->nullable()->after('is_active');
            $table->timestamp('valid_until')->nullable()->after('valid_from');
        });

        DB::table('reward_programs')->where('slug', 'referral')->update([
            'description' => 'Recompensa por indicar um novo cliente que complete o onboarding e fique activo. Termos e valor podem ser actualizados neste programa.',
        ]);
    }

    public function down(): void
    {
        Schema::table('reward_programs', function (Blueprint $table) {
            $table->dropColumn(['description', 'valid_from', 'valid_until']);
        });
    }
};
