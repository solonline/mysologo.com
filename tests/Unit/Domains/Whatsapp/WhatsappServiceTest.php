<?php

namespace Tests\Unit\Domains\Whatsapp;

use App\Domains\Whatsapp\Clients\EvolutionHttpClient;
use App\Domains\Whatsapp\Contracts\WhatsappClientInterface;
use App\Domains\Whatsapp\Contracts\WhatsappGatewayInterface;
use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Domains\Whatsapp\Enums\WhatsappMediaType;
use App\Domains\Whatsapp\Gateways\WhatsappGateway;
use App\Domains\Whatsapp\Jobs\SendWhatsappMediaJob;
use App\Domains\Whatsapp\Jobs\SendWhatsappTextJob;
use App\Domains\Whatsapp\Services\WhatsappService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WhatsappServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_container_binds_evolution_stack(): void
    {
        $this->assertInstanceOf(EvolutionHttpClient::class, $this->app->make(WhatsappClientInterface::class));
        $this->assertInstanceOf(WhatsappGateway::class, $this->app->make(WhatsappGatewayInterface::class));
        $this->assertInstanceOf(WhatsappService::class, $this->app->make(WhatsappService::class));
    }

    public function test_send_text_delegates_to_gateway(): void
    {
        $payload = WhatsappSendResult::fromProvider(201, [
            'key' => ['id' => 'MSG1'],
            'status' => 'PENDING',
        ]);

        $gateway = Mockery::mock(WhatsappGatewayInterface::class);
        $gateway->shouldReceive('sendText')
            ->once()
            ->with('351912345678', 'Olá')
            ->andReturn($payload);

        $result = (new WhatsappService($gateway))->sendText('351912345678', 'Olá');

        $this->assertSame($payload, $result);
    }

    public function test_send_text_later_dispatches_job(): void
    {
        Queue::fake();

        $gateway = Mockery::mock(WhatsappGatewayInterface::class);
        $gateway->shouldNotReceive('sendText');

        (new WhatsappService($gateway))->sendTextLater('351912345678', 'Olá');

        Queue::assertPushed(SendWhatsappTextJob::class, function (SendWhatsappTextJob $job) {
            return $job->to === '351912345678'
                && $job->text === 'Olá'
                && $job->queue === 'whatsapp';
        });
    }

    public function test_send_media_later_dispatches_job(): void
    {
        Queue::fake();

        $gateway = Mockery::mock(WhatsappGatewayInterface::class);

        (new WhatsappService($gateway))->sendMediaLater(
            '351912345678',
            'https://cdn.example/a.png',
            WhatsappMediaType::Image,
            'Legenda',
        );

        Queue::assertPushed(SendWhatsappMediaJob::class, function (SendWhatsappMediaJob $job) {
            return $job->to === '351912345678'
                && $job->media === 'https://cdn.example/a.png'
                && $job->mediaType === WhatsappMediaType::Image
                && $job->caption === 'Legenda'
                && $job->queue === 'whatsapp';
        });
    }

    public function test_is_connected_reads_gateway_state(): void
    {
        $gateway = Mockery::mock(WhatsappGatewayInterface::class);
        $gateway->shouldReceive('connectionState')
            ->once()
            ->andReturn([
                'connected' => true,
                'state' => 'open',
                'body' => [],
                'http_code' => 200,
            ]);

        $this->assertTrue((new WhatsappService($gateway))->isConnected());
    }
}
