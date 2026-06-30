<?php

namespace App\Domains\Settings\Services;

class DiditSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function apiKey(): string
    {
        return $this->resolve('didit.api_key', 'services.didit.api_key');
    }

    public function apiSecret(): string
    {
        return $this->resolve('didit.api_secret', 'services.didit.api_secret');
    }

    public function baseUrl(): string
    {
        return $this->resolve('didit.base_url', 'services.didit.base_url');
    }

    public function workflowId(): string
    {
        return $this->resolve('didit.workflow_id', 'services.didit.workflow_id');
    }

    public function workflowKybId(): string
    {
        return $this->resolve('didit.workflow_kyb_id', 'services.didit.workflow_kyb_id');
    }

    public function webhookSecret(): string
    {
        return $this->resolve('didit.webhook_secret', 'services.didit.webhook_secret');
    }

    public function isKybWorkflow(?string $workflowId): bool
    {
        if ($workflowId === null || $workflowId === '') {
            return false;
        }

        $kybId = $this->workflowKybId();

        return $kybId !== '' && $workflowId === $kybId;
    }

    private function resolve(string $settingKey, string $configKey): string
    {
        $value = $this->settings->getByKey($settingKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        return (string) config($configKey, '');
    }
}
