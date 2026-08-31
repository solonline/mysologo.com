<?php

namespace Tests\Feature\Domains\Settings;

use App\Domains\Settings\Mail\SmtpTestMail;
use App\Domains\Settings\Services\MailSettingsService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedMailSettings();
    }

    public function test_mail_settings_service_prefers_database_over_env(): void
    {
        config([
            'mail.mailers.smtp.host' => 'env.smtp.test',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => 'env-user',
            'mail.mailers.smtp.password' => 'env-pass',
            'mail.mailers.smtp.encryption' => 'ssl',
            'mail.from.address' => 'env@example.com',
            'mail.from.name' => 'Env Name',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'mail.host'],
            ['value' => 'smtp.yoop.test', 'type' => 'text', 'label' => 'Host SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.port'],
            ['value' => '587', 'type' => 'number', 'label' => 'Porta SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.encryption'],
            ['value' => 'tls', 'type' => 'select', 'label' => 'Encriptação'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.username'],
            ['value' => 'db-user', 'type' => 'text', 'label' => 'Utilizador SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.password'],
            ['value' => 'db-pass', 'type' => 'secret', 'label' => 'Palavra-passe SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_address'],
            ['value' => 'noreply@yoop.test', 'type' => 'email', 'label' => 'Email remetente'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_name'],
            ['value' => 'Yoop Clientes', 'type' => 'text', 'label' => 'Nome remetente'],
        );

        $service = app(MailSettingsService::class);

        $this->assertSame('smtp.yoop.test', $service->host());
        $this->assertSame(587, $service->port());
        $this->assertSame('tls', $service->encryption());
        $this->assertSame('smtp', $service->scheme());
        $this->assertSame('db-user', $service->username());
        $this->assertSame('db-pass', $service->password());
        $this->assertSame('noreply@yoop.test', $service->fromAddress());
        $this->assertSame('Yoop Clientes', $service->fromName());
        $this->assertTrue($service->isConfigured());
    }

    public function test_mail_settings_service_falls_back_to_env_when_database_empty(): void
    {
        Setting::query()->where('key', 'like', 'mail.%')->delete();

        config([
            'mail.mailers.smtp.host' => 'env-only.smtp.test',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.username' => 'env-only-user',
            'mail.mailers.smtp.password' => 'env-only-pass',
            'mail.mailers.smtp.encryption' => 'ssl',
            'mail.from.address' => 'env-only@example.com',
            'mail.from.name' => 'Env Only',
        ]);

        $service = app(MailSettingsService::class);

        $this->assertSame('env-only.smtp.test', $service->host());
        $this->assertSame(465, $service->port());
        $this->assertSame('ssl', $service->encryption());
        $this->assertSame('smtps', $service->scheme());
        $this->assertSame('env-only-user', $service->username());
        $this->assertSame('env-only-pass', $service->password());
        $this->assertSame('env-only@example.com', $service->fromAddress());
        $this->assertSame('Env Only', $service->fromName());
        $this->assertFalse($service->isConfigured());
    }

    public function test_apply_writes_smtp_config_without_changing_test_mailer(): void
    {
        config(['mail.default' => 'array']);

        Setting::query()->updateOrCreate(
            ['key' => 'mail.host'],
            ['value' => 'smtp.apply.test', 'type' => 'text', 'label' => 'Host SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.port'],
            ['value' => '587', 'type' => 'number', 'label' => 'Porta SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.encryption'],
            ['value' => 'ssl', 'type' => 'select', 'label' => 'Encriptação'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.username'],
            ['value' => 'apply-user', 'type' => 'text', 'label' => 'Utilizador SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.password'],
            ['value' => 'apply-pass', 'type' => 'secret', 'label' => 'Palavra-passe SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_address'],
            ['value' => 'apply@yoop.test', 'type' => 'email', 'label' => 'Email remetente'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_name'],
            ['value' => 'Apply Name', 'type' => 'text', 'label' => 'Nome remetente'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.verify_peer'],
            ['value' => 'false', 'type' => 'toggle', 'label' => 'Verificar certificado SSL'],
        );

        app(MailSettingsService::class)->apply();

        $this->assertSame('array', config('mail.default'));
        $this->assertSame('smtp.apply.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('apply-user', config('mail.mailers.smtp.username'));
        $this->assertSame('apply-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('apply@yoop.test', config('mail.from.address'));
        $this->assertSame('Apply Name', config('mail.from.name'));
        $this->assertFalse(config('mail.mailers.smtp.verify_peer'));
        $this->assertFalse(config('mail.mailers.smtp.stream.ssl.verify_peer'));
        $this->assertTrue(config('mail.mailers.smtp.stream.ssl.allow_self_signed'));
    }

    public function test_dashboard_settings_page_shows_mail_tab_and_saves_values(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->withoutVite()
            ->actingAs($user, 'web')
            ->get(route('dashboard.settings', absolute: false))
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('SMTP (email de clientes)')
            ->assertSee('Host SMTP')
            ->assertSee('Palavra-passe SMTP')
            ->assertSee('Enviar email de teste')
            ->assertSee('name="to"', false);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.settings.update', absolute: false), [
                'settings' => [
                    'mail.host' => 'smtp.dashboard.test',
                    'mail.port' => '465',
                    'mail.encryption' => 'ssl',
                    'mail.username' => 'dashboard-user',
                    'mail.password' => 'dashboard-pass',
                    'mail.from_address' => 'hello@yoop.test',
                    'mail.from_name' => 'Yoop',
                ],
            ])
            ->assertRedirect(route('dashboard.settings'));

        $this->assertSame('smtp.dashboard.test', Setting::query()->where('key', 'mail.host')->value('value'));
        $this->assertSame('465', Setting::query()->where('key', 'mail.port')->value('value'));
        $this->assertSame('ssl', Setting::query()->where('key', 'mail.encryption')->value('value'));
        $this->assertSame('dashboard-user', Setting::query()->where('key', 'mail.username')->value('value'));
        $this->assertSame('dashboard-pass', Setting::query()->where('key', 'mail.password')->value('value'));
        $this->assertSame('hello@yoop.test', Setting::query()->where('key', 'mail.from_address')->value('value'));
        $this->assertSame('Yoop', Setting::query()->where('key', 'mail.from_name')->value('value'));
        $this->assertSame('smtp.dashboard.test', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_dashboard_can_send_test_mail_to_recipient(): void
    {
        Mail::fake();

        Setting::query()->updateOrCreate(
            ['key' => 'mail.host'],
            ['value' => 'smtp.test.local', 'type' => 'text', 'label' => 'Host SMTP'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_address'],
            ['value' => 'noreply@yoop.test', 'type' => 'email', 'label' => 'Email remetente'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'mail.from_name'],
            ['value' => 'Yoop Test', 'type' => 'text', 'label' => 'Nome remetente'],
        );

        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.settings.test-mail', absolute: false), [
                'to' => 'qa@yoop.test',
            ])
            ->assertRedirect(route('dashboard.settings'))
            ->assertSessionHas('success')
            ->assertSessionHas('settings_tab', 'mail');

        Mail::assertSent(SmtpTestMail::class, function (SmtpTestMail $mail) {
            return $mail->hasTo('qa@yoop.test');
        });
    }

    public function test_dashboard_test_mail_requires_recipient(): void
    {
        $user = $this->userWithPermissions(['settings.view', 'settings.edit']);

        $this->actingAs($user, 'web')
            ->post(route('dashboard.settings.test-mail', absolute: false), [
                'to' => '',
            ])
            ->assertRedirect(route('dashboard.settings'))
            ->assertSessionHasErrors('to')
            ->assertSessionHas('settings_tab', 'mail');
    }

    private function seedMailSettings(): void
    {
        $settings = [
            ['key' => 'mail.host', 'label' => 'Host SMTP', 'type' => 'text', 'value' => ''],
            ['key' => 'mail.port', 'label' => 'Porta SMTP', 'type' => 'number', 'value' => '587'],
            ['key' => 'mail.encryption', 'label' => 'Encriptação', 'type' => 'select', 'value' => 'tls'],
            ['key' => 'mail.username', 'label' => 'Utilizador SMTP', 'type' => 'text', 'value' => ''],
            ['key' => 'mail.password', 'label' => 'Palavra-passe SMTP', 'type' => 'secret', 'value' => ''],
            ['key' => 'mail.from_address', 'label' => 'Email remetente', 'type' => 'email', 'value' => ''],
            ['key' => 'mail.from_name', 'label' => 'Nome remetente', 'type' => 'text', 'value' => 'Yoop'],
            ['key' => 'mail.verify_peer', 'label' => 'Verificar certificado SSL', 'type' => 'toggle', 'value' => 'false'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                ],
            );
        }
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo(
            collect($permissions)->map(fn (string $permission) => Permission::findByName($permission, 'web'))->all()
        );

        return $user;
    }
}
