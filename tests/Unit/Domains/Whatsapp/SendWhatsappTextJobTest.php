<?php

namespace Tests\Unit\Domains\Whatsapp;

use App\Domains\Whatsapp\DTOs\WhatsappSendResult;
use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Jobs\SendWhatsappTextJob;
use App\Domains\Whatsapp\Services\WhatsappService;
use Mockery;
use Tests\TestCase;

class SendWhatsappTextJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_does_not_throw_when_send_succeeds(): void
    {
        $service = Mockery::mock(WhatsappService::class);
        $service->shouldReceive('sendText')
            ->once()
            ->with('351912345678', 'Olá')
            ->andReturn(WhatsappSendResult::fromProvider(201, [
                'key' => ['id' => 'OK'],
                'status' => 'PENDING',
            ]));

        (new SendWhatsappTextJob('351912345678', 'Olá'))->handle($service);

        $this->assertTrue(true);
    }

    public function test_does_not_retry_permanent_failures(): void
    {
        $service = Mockery::mock(WhatsappService::class);
        $service->shouldReceive('sendText')
            ->once()
            ->andReturn(WhatsappSendResult::failed('WhatsApp provider is not configured.'));

        (new SendWhatsappTextJob('351912345678', 'Olá'))->handle($service);

        $this->assertTrue(true);
    }

    public function test_throws_on_retryable_failure(): void
    {
        $service = Mockery::mock(WhatsappService::class);
        $service->shouldReceive('sendText')
            ->once()
            ->andReturn(WhatsappSendResult::failed('Erro de comunicação com o serviço de WhatsApp.'));

        $this->expectException(WhatsappException::class);
        $this->expectExceptionMessage('Erro de comunicação com o serviço de WhatsApp.');

        (new SendWhatsappTextJob('351912345678', 'Olá'))->handle($service);
    }
}
