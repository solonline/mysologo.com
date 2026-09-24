<?php

namespace Tests\Unit\Domains\Whatsapp;

use App\Domains\Whatsapp\Contracts\WhatsappClientInterface;
use App\Domains\Whatsapp\Enums\WhatsappMediaType;
use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Gateways\WhatsappGateway;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use Mockery;
use Tests\TestCase;

class WhatsappGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_normalizes_number_and_delegates_text_to_client(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldReceive('sendText')
            ->once()
            ->with('351912345678', 'Olá Yoop')
            ->andReturn([
                'http_code' => 201,
                'body' => [
                    'key' => ['id' => 'BAE5OK'],
                    'status' => 'PENDING',
                ],
            ]);

        $result = $this->gateway($client)->sendText('+351 912 345 678', 'Olá Yoop');

        $this->assertTrue($result->success);
        $this->assertSame(201, $result->httpCode);
        $this->assertSame('BAE5OK', $result->messageId);
        $this->assertSame('PENDING', $result->status);
    }

    public function test_rejects_empty_number_without_calling_client(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldNotReceive('sendText');

        $result = $this->gateway($client)->sendText('abc', 'Olá');

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpCode);
    }

    public function test_skips_send_when_credentials_are_missing(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldNotReceive('sendText');

        $result = $this->gateway($client, configured: false)->sendText('351912345678', 'Olá');

        $this->assertFalse($result->success);
        $this->assertSame('WhatsApp provider is not configured.', $result->error);
        $this->assertTrue($result->isPermanentFailure());
    }

    public function test_maps_provider_error_body(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldReceive('sendText')
            ->once()
            ->andReturn([
                'http_code' => 400,
                'body' => [
                    'status' => 400,
                    'error' => 'Bad Request',
                    'response' => ['message' => ['Invalid number']],
                ],
            ]);

        $result = $this->gateway($client)->sendText('351912345678', 'Olá');

        $this->assertFalse($result->success);
        $this->assertSame('Invalid number', $result->error);
        $this->assertTrue($result->isPermanentFailure());
    }

    public function test_send_media_delegates_to_client(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldReceive('sendMedia')
            ->once()
            ->with('351912345678', 'https://cdn.example/a.png', 'image', 'Legenda', 'a.png', 'image/png')
            ->andReturn([
                'http_code' => 201,
                'body' => ['key' => ['id' => 'IMG1'], 'status' => 'PENDING'],
            ]);

        $result = $this->gateway($client)->sendMedia(
            '351912345678',
            'https://cdn.example/a.png',
            WhatsappMediaType::Image,
            'Legenda',
            'a.png',
            'image/png',
        );

        $this->assertTrue($result->success);
        $this->assertSame('IMG1', $result->messageId);
    }

    public function test_connection_state_open_is_connected(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldReceive('connectionState')
            ->once()
            ->andReturn([
                'http_code' => 200,
                'body' => ['instance' => ['instanceName' => 'yoop', 'state' => 'open']],
            ]);

        $state = $this->gateway($client)->connectionState();

        $this->assertTrue($state['connected']);
        $this->assertSame('open', $state['state']);
        $this->assertSame(200, $state['http_code']);
    }

    public function test_wraps_client_exceptions_as_failed_result(): void
    {
        $client = Mockery::mock(WhatsappClientInterface::class);
        $client->shouldReceive('sendText')
            ->once()
            ->andThrow(new WhatsappException('Erro de comunicação com a Evolution API.'));

        $result = $this->gateway($client)->sendText('351912345678', 'Olá');

        $this->assertFalse($result->success);
        $this->assertSame('Erro de comunicação com a Evolution API.', $result->error);
        $this->assertFalse($result->isPermanentFailure());
    }

    private function gateway(WhatsappClientInterface $client, bool $configured = true): WhatsappGateway
    {
        $settings = Mockery::mock(WhatsappSettingsService::class);
        $settings->shouldReceive('hasCredentials')->andReturn($configured);

        return new WhatsappGateway($client, $settings);
    }
}
