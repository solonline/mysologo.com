<?php

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('status', 50)
                ->default(CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION->value)
                ->change();
        });

        $legacyMap = [
            'pending' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION->value,
            'PENDING' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION->value,
            'analysing' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW->value,
            'ANALYSIS' => CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW->value,
            'active' => CustomerLifecycleStatus::ACTIVE->value,
            'ACTIVE' => CustomerLifecycleStatus::ACTIVE->value,
            'inactive' => CustomerLifecycleStatus::SUSPENDED->value,
            'INACTIVE' => CustomerLifecycleStatus::SUSPENDED->value,
            'blocked' => CustomerLifecycleStatus::BLOCKED->value,
            'BLOCKED' => CustomerLifecycleStatus::BLOCKED->value,
        ];

        foreach ($legacyMap as $old => $new) {
            DB::table('customers')->where('status', $old)->update(['status' => $new]);
        }

        DB::table('customers')
            ->where('status', CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION->value)
            ->whereNotNull('email_verified_at')
            ->update(['status' => CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION->value]);
    }

    public function down(): void
    {
        $reverseMap = [
            CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION->value => 'pending',
            CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION->value => 'pending',
            CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION->value => 'pending',
            CustomerLifecycleStatus::IDENTITY_UNDER_REVIEW->value => 'analysing',
            CustomerLifecycleStatus::IDENTITY_REJECTED->value => 'blocked',
            CustomerLifecycleStatus::PROVISIONING->value => 'analysing',
            CustomerLifecycleStatus::ACTIVE->value => 'active',
            CustomerLifecycleStatus::SUSPENDED->value => 'inactive',
            CustomerLifecycleStatus::BLOCKED->value => 'blocked',
        ];

        foreach ($reverseMap as $new => $old) {
            DB::table('customers')->where('status', $new)->update(['status' => $old]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['pending', 'analysing', 'active', 'inactive', 'blocked'])
                ->default('pending')
                ->change();
        });
    }
};
