<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $referralProgramId = DB::table('reward_programs')->insertGetId([
            'slug' => 'referral',
            'name' => 'Indicação',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('reward_programs')->restrictOnDelete();
            $table->foreignId('referred_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('qualified_at')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique('referred_id');
            $table->index(['customer_id', 'status']);
            $table->index(['program_id', 'status']);
        });

        if (Schema::hasTable('referral_rewards')) {
            $rows = DB::table('referral_rewards')->orderBy('id')->get();

            foreach ($rows as $row) {
                DB::table('rewards')->insert([
                    'customer_id' => $row->referrer_id,
                    'program_id' => $referralProgramId,
                    'referred_id' => $row->referred_id,
                    'status' => $row->status,
                    'qualified_at' => $row->qualified_at,
                    'amount_cents' => $row->amount_cents,
                    'currency' => $row->currency,
                    'paid_at' => $row->paid_at,
                    'ledger_entry_id' => $row->ledger_entry_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('referral_rewards');
        }
    }

    public function down(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('referred_id')->constrained('customers')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('qualified_at')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique('referred_id');
            $table->index(['referrer_id', 'status']);
        });

        if (Schema::hasTable('rewards') && Schema::hasTable('reward_programs')) {
            $referralProgramId = DB::table('reward_programs')->where('slug', 'referral')->value('id');

            $rows = DB::table('rewards')
                ->when($referralProgramId, fn ($query) => $query->where('program_id', $referralProgramId))
                ->whereNotNull('referred_id')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                DB::table('referral_rewards')->insert([
                    'referrer_id' => $row->customer_id,
                    'referred_id' => $row->referred_id,
                    'status' => $row->status,
                    'qualified_at' => $row->qualified_at,
                    'amount_cents' => $row->amount_cents,
                    'currency' => $row->currency,
                    'paid_at' => $row->paid_at,
                    'ledger_entry_id' => $row->ledger_entry_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('rewards');
        Schema::dropIfExists('reward_programs');
    }
};
