<?php

namespace Tests\Unit\Domains\Whatsapp;

use App\Domains\Settings\Services\SettingsService;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use Mockery;
use Tests\TestCase;

class WhatsappSettingsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_reads_settings_and_trims_base_url(): void
    {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('getByKey')->with('whatsapp.base_url')->andReturn('https://evolution.test/');
        $settings->shouldReceive('getByKey')->with('whatsapp.api_key')->andReturn(' secret-key ');
        $settings->shouldReceive('getByKey')->with('whatsapp.instance')->andReturn(' yoop ');
        $settings->shouldReceive('getByKey')->with('whatsapp.timeout_seconds')->andReturn('20');

        $service = new WhatsappSettingsService($settings);

        $this->assertSame('https://evolution.test', $service->baseUrl());
        $this->assertSame('secret-key', $service->apiKey());
        $this->assertSame('yoop', $service->instance());
        $this->assertSame(20, $service->timeoutSeconds());
        $this->assertTrue($service->hasCredentials());
    }

    public function test_falls_back_to_config_when_settings_are_empty(): void
    {
        config([
            'services.whatsapp.base_url' => 'https://from-config.test',
            'services.whatsapp.api_key' => 'cfg-key',
            'services.whatsapp.instance' => 'cfg-instance',
            'services.whatsapp.timeout_seconds' => 12,
        ]);

        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('getByKey')->andReturn('');

        $service = new WhatsappSettingsService($settings);

        $this->assertSame('https://from-config.test', $service->baseUrl());
        $this->assertSame('cfg-key', $service->apiKey());
        $this->assertSame('cfg-instance', $service->instance());
        $this->assertSame(12, $service->timeoutSeconds());
        $this->assertTrue($service->hasCredentials());
    }

    public function test_has_credentials_is_false_when_incomplete(): void
    {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('getByKey')->with('whatsapp.base_url')->andReturn('https://evolution.test');
        $settings->shouldReceive('getByKey')->with('whatsapp.api_key')->andReturn('');
        $settings->shouldReceive('getByKey')->with('whatsapp.instance')->andReturn('yoop');

        config([
            'services.whatsapp.api_key' => '',
        ]);

        $service = new WhatsappSettingsService($settings);

        $this->assertFalse($service->hasCredentials());
    }
}
