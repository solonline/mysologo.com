<?php

namespace Tests\Unit\Domains\Whatsapp;

use App\Domains\Whatsapp\Clients\EvolutionHttpClient;
use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Services\WhatsappSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class EvolutionHttpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sends_text_with_apikey_header(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame('https://evolution.test/message/sendText/yoop', $request->url());
            $this->assertTrue($request->hasHeader('apikey', 'test-key'));
            $this->assertSame(['number' => '351912345678', 'text' => 'Olá'], $request->data());

            return Http::response([
                'key' => ['id' => 'BAE5TEST', 'remoteJid' => '351912345678@s.whatsapp.net', 'fromMe' => true],
                'status' => 'PENDING',
            ], 201);
        });

        $response = $this->client()->sendText('351912345678', 'Olá');

        $this->assertSame(201, $response['http_code']);
        $this->assertSame('BAE5TEST', $response['body']['key']['id']);
        $this->assertSame('PENDING', $response['body']['status']);
    }

    public function test_sends_media_payload(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://evolution.test/message/sendMedia/yoop' => Http::response([
                'key' => ['id' => 'MEDIA1'],
                'status' => 'PENDING',
            ], 201),
        ]);

        $response = $this->client()->sendMedia(
            '351912345678',
            'https://cdn.example/doc.pdf',
            'document',
            'Comprovativo',
            'comprovativo.pdf',
            'application/pdf',
        );

        $this->assertSame(201, $response['http_code']);
        $this->assertSame('MEDIA1', $response['body']['key']['id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://evolution.test/message/sendMedia/yoop'
                && $request->data()['mediatype'] === 'document'
                && $request->data()['fileName'] === 'comprovativo.pdf'
                && $request->data()['caption'] === 'Comprovativo';
        });
    }

    public function test_reads_connection_state(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://evolution.test/instance/connectionState/yoop' => Http::response([
                'instance' => ['instanceName' => 'yoop', 'state' => 'open'],
            ], 200),
        ]);

        $response = $this->client()->connectionState();

        $this->assertSame(200, $response['http_code']);
        $this->assertSame('open', $response['body']['instance']['state']);
    }

    public function test_wraps_http_exceptions(): void
    {
        Http::preventStrayRequests();
        Http::fake(function () {
            throw new ConnectionException('connection reset');
        });

        $this->expectException(WhatsappException::class);
        $this->expectExceptionMessage('Erro de comunicação com a Evolution API.');

        $this->client()->sendText('351912345678', 'Olá');
    }

    private function client(): EvolutionHttpClient
    {
        $settings = Mockery::mock(WhatsappSettingsService::class);
        $settings->shouldReceive('baseUrl')->andReturn('https://evolution.test');
        $settings->shouldReceive('apiKey')->andReturn('test-key');
        $settings->shouldReceive('instance')->andReturn('yoop');
        $settings->shouldReceive('timeoutSeconds')->andReturn(15);

        return new EvolutionHttpClient($settings);
    }
}
