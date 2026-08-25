<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reconciliation_runs');

        foreach (['reconciliation.view', 'reconciliation.apply'] as $name) {
            Permission::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('source')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('status');
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'reconciliation.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'reconciliation.apply', 'guard_name' => 'web']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
