<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountsPayableDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Setting::create([
            'key' => 'currency.accepted',
            'value' => json_encode(['EUR', 'USD']),
        ]);
    }

    public function test_accounts_payable_index_requires_permission(): void
    {
        $this->get(route('dashboard.accounts-payable'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.accounts-payable'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_create_simple_account_payable(): void
    {
        $user = $this->userWithPermissions(['accounts_payable.view', 'accounts_payable.create']);

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.store'), $this->payload([
                'supplier' => 'Fornecedor A',
                'description' => 'Licença anual',
                'amount' => '199.90',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('account_payables', [
            'supplier' => 'Fornecedor A',
            'description' => 'Licença anual',
            'amount' => '199.90',
            'currency' => 'EUR',
            'status' => AccountPayable::STATUS_PENDING,
            'is_recurring' => false,
            'occurrence_index' => 1,
            'occurrence_total' => 1,
        ]);
    }

    public function test_creates_recurring_accounts_for_common_intervals_with_end_date(): void
    {
        $user = $this->userWithPermissions(['accounts_payable.view', 'accounts_payable.create']);

        $cases = [
            AccountPayable::RECURRENCE_WEEKLY => ['due_date' => '2026-01-01', 'recurrence_ends_at' => '2026-01-22', 'total' => 4],
            AccountPayable::RECURRENCE_MONTHLY => ['due_date' => '2026-01-31', 'recurrence_ends_at' => '2026-03-31', 'total' => 3],
            AccountPayable::RECURRENCE_QUARTERLY => ['due_date' => '2026-01-31', 'recurrence_ends_at' => '2026-07-31', 'total' => 3],
            AccountPayable::RECURRENCE_YEARLY => ['due_date' => '2026-02-28', 'recurrence_ends_at' => '2028-02-28', 'total' => 3],
        ];

        foreach ($cases as $interval => $case) {
            $this->actingAs($user)
                ->post(route('dashboard.accounts-payable.store'), $this->payload([
                    'description' => "Recorrência {$interval}",
                    'due_date' => $case['due_date'],
                    'is_recurring' => '1',
                    'recurrence_interval' => $interval,
                    'recurrence_ends_at' => $case['recurrence_ends_at'],
                ]))
                ->assertRedirect();

            $this->assertSame(
                $case['total'],
                AccountPayable::where('description', "Recorrência {$interval}")->count()
            );
        }

        $this->assertDatabaseHas('account_payables', [
            'description' => 'Recorrência monthly',
            'due_date' => '2026-02-28',
            'occurrence_index' => 2,
            'occurrence_total' => 3,
        ]);
    }

    public function test_creates_recurring_accounts_with_occurrence_limit(): void
    {
        $user = $this->userWithPermissions(['accounts_payable.view', 'accounts_payable.create']);

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.store'), $this->payload([
                'description' => 'Renda mensal',
                'is_recurring' => '1',
                'recurrence_interval' => AccountPayable::RECURRENCE_MONTHLY,
                'recurrence_occurrences' => 5,
            ]))
            ->assertRedirect();

        $accounts = AccountPayable::where('description', 'Renda mensal')->orderBy('occurrence_index')->get();

        $this->assertCount(5, $accounts);
        $this->assertTrue($accounts->pluck('recurrence_group_uuid')->unique()->count() === 1);
        $this->assertSame([1, 2, 3, 4, 5], $accounts->pluck('occurrence_index')->map(fn ($value): int => (int) $value)->all());
        $this->assertTrue($accounts->every(fn (AccountPayable $account): bool => (int) $account->occurrence_total === 5));
    }

    public function test_recurring_accounts_stop_at_the_first_limit(): void
    {
        $user = $this->userWithPermissions(['accounts_payable.view', 'accounts_payable.create']);

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.store'), $this->payload([
                'description' => 'Servidor',
                'due_date' => '2026-01-31',
                'is_recurring' => '1',
                'recurrence_interval' => AccountPayable::RECURRENCE_MONTHLY,
                'recurrence_ends_at' => '2026-12-31',
                'recurrence_occurrences' => 2,
            ]))
            ->assertRedirect();

        $this->assertSame(2, AccountPayable::where('description', 'Servidor')->count());

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.store'), $this->payload([
                'description' => 'Data center',
                'due_date' => '2026-01-31',
                'is_recurring' => '1',
                'recurrence_interval' => AccountPayable::RECURRENCE_MONTHLY,
                'recurrence_ends_at' => '2026-03-31',
                'recurrence_occurrences' => 10,
            ]))
            ->assertRedirect();

        $this->assertSame(3, AccountPayable::where('description', 'Data center')->count());
    }

    public function test_can_mark_paid_and_cancel_account_payable(): void
    {
        $user = $this->userWithPermissions([
            'accounts_payable.view',
            'accounts_payable.edit',
            'accounts_payable.pay',
        ]);
        $account = AccountPayable::create($this->payload());

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.paid', $account), ['paid_at' => '2026-02-01'])
            ->assertRedirect();

        $this->assertDatabaseHas('account_payables', [
            'id' => $account->id,
            'status' => AccountPayable::STATUS_PAID,
            'paid_at' => '2026-02-01',
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.cancel', $account))
            ->assertRedirect();

        $this->assertDatabaseHas('account_payables', [
            'id' => $account->id,
            'status' => AccountPayable::STATUS_CANCELLED,
            'paid_at' => null,
        ]);
    }

    public function test_can_upload_download_and_remove_attachments(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions(['accounts_payable.view', 'accounts_payable.edit']);
        $account = AccountPayable::create($this->payload());

        $this->actingAs($user)
            ->post(route('dashboard.accounts-payable.attachments.store', $account), [
                'attachments' => [
                    UploadedFile::fake()->create('invoice.pdf', 24, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $attachment = $account->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($user)
            ->get(route('dashboard.accounts-payable.attachments.download', [$account, $attachment]))
            ->assertOk();

        $this->actingAs($user)
            ->delete(route('dashboard.accounts-payable.attachments.destroy', [$account, $attachment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('account_payable_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'Fornecedor Teste',
            'description' => 'Conta teste',
            'category' => 'Operações',
            'document_number' => 'FT-2026-001',
            'issued_at' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => '100.00',
            'currency' => 'EUR',
            'status' => AccountPayable::STATUS_PENDING,
            'paid_at' => null,
            'notes' => 'Observação interna',
            'is_recurring' => '0',
            'recurrence_interval' => null,
            'recurrence_ends_at' => null,
            'recurrence_occurrences' => null,
        ], $overrides);
    }
}
