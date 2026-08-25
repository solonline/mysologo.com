<?php

namespace App\Domains\Settings\Services;

use App\Domains\Settings\Mail\SmtpTestMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MailSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function host(): string
    {
        return $this->resolve('mail.host', 'mail.mailers.smtp.host');
    }

    public function port(): int
    {
        $value = $this->resolve('mail.port', 'mail.mailers.smtp.port', '587');

        return (int) ($value !== '' ? $value : 587);
    }

    public function username(): string
    {
        return $this->resolve('mail.username', 'mail.mailers.smtp.username');
    }

    public function password(): string
    {
        return $this->resolve('mail.password', 'mail.mailers.smtp.password');
    }

    public function encryption(): string
    {
        $value = strtolower($this->resolve('mail.encryption', 'mail.mailers.smtp.encryption'));

        return in_array($value, ['tls', 'ssl'], true) ? $value : '';
    }

    public function scheme(): string
    {
        return $this->encryption() === 'ssl' ? 'smtps' : 'smtp';
    }

    public function fromAddress(): string
    {
        $value = $this->resolve('mail.from_address', 'mail.from.address');

        if ($value !== '') {
            return $value;
        }

        return (string) ($this->settings->getByKey('company.email') ?? '');
    }

    public function fromName(): string
    {
        $value = $this->resolve('mail.from_name', 'mail.from.name');

        if ($value !== '') {
            return $value;
        }

        return (string) ($this->settings->getByKey('company.name') ?? config('app.name', 'Yoop'));
    }

    public function verifyPeer(): bool
    {
        $value = $this->settings->getByKey('mail.verify_peer');

        if ($value === null || $value === '') {
            return (bool) config('mail.mailers.smtp.verify_peer', false);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function isConfigured(): bool
    {
        $host = $this->settings->getByKey('mail.host');

        return is_string($host) && trim($host) !== '';
    }

    public function apply(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $verifyPeer = $this->verifyPeer();

        config([
            'mail.mailers.smtp.host' => $this->host(),
            'mail.mailers.smtp.port' => $this->port(),
            'mail.mailers.smtp.username' => $this->username() !== '' ? $this->username() : null,
            'mail.mailers.smtp.password' => $this->password() !== '' ? $this->password() : null,
            'mail.mailers.smtp.scheme' => $this->scheme(),
            'mail.mailers.smtp.encryption' => $this->encryption() !== '' ? $this->encryption() : null,
            'mail.mailers.smtp.verify_peer' => $verifyPeer,
            'mail.mailers.smtp.stream' => [
                'ssl' => [
                    'allow_self_signed' => ! $verifyPeer,
                    'verify_peer' => $verifyPeer,
                    'verify_peer_name' => $verifyPeer,
                ],
            ],
        ]);

        if ($this->fromAddress() !== '') {
            config(['mail.from.address' => $this->fromAddress()]);
        }

        if ($this->fromName() !== '') {
            config(['mail.from.name' => $this->fromName()]);
        }

        if (! app()->runningUnitTests()) {
            config(['mail.default' => 'smtp']);
        }

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
    }

    public function sendTest(string $to): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Configure o host SMTP antes de enviar um email de teste.');
        }

        $this->apply();

        Mail::to($to)->send(new SmtpTestMail(
            smtpHost: $this->host(),
            smtpPort: $this->port(),
            smtpEncryption: $this->encryption() ?: 'nenhuma',
        ));
    }

    private function resolve(string $settingKey, string $configKey, string $default = ''): string
    {
        $value = $this->settings->getByKey($settingKey);

        if ($value !== null && $value !== '') {
            return trim((string) $value);
        }

        $fallback = config($configKey, $default);

        if ($fallback === null || $fallback === '') {
            return $default;
        }

        return trim((string) $fallback);
    }
}
