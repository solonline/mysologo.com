<?php

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            $table->string('status', 20)
                ->default(IntegrationAccountStatus::PENDING->value)
                ->change();
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->string('status', 20)
                ->default(IntegrationAccountStatus::PENDING->value)
                ->change();
        });

        $map = [
            'active' => IntegrationAccountStatus::ACTIVE->value,
            'inactive' => IntegrationAccountStatus::SUSPENDED->value,
            'blocked' => IntegrationAccountStatus::SUSPENDED->value,
        ];

        foreach ($map as $old => $new) {
            DB::table('customer_stripe_accounts')->where('status', $old)->update(['status' => $new]);
            DB::table('customer_wallester_accounts')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        $reverse = [
            IntegrationAccountStatus::ACTIVE->value => 'active',
            IntegrationAccountStatus::SUSPENDED->value => 'inactive',
            IntegrationAccountStatus::PENDING->value => 'active',
            IntegrationAccountStatus::RESTRICTED->value => 'active',
            IntegrationAccountStatus::FAILED->value => 'inactive',
            IntegrationAccountStatus::CLOSED->value => 'inactive',
        ];

        foreach ($reverse as $new => $old) {
            DB::table('customer_stripe_accounts')->where('status', $new)->update(['status' => $old]);
            DB::table('customer_wallester_accounts')->where('status', $new)->update(['status' => $old]);
        }

        Schema::table('customer_stripe_accounts', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active')->change();
        });

        Schema::table('customer_wallester_accounts', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }
};
