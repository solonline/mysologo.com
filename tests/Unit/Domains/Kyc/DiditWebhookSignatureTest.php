<?php

namespace Tests\Unit\Domains\Kyc;

use App\Domains\Kyc\DiditWebhookSignature;
use Tests\TestCase;

class DiditWebhookSignatureTest extends TestCase
{
    public function test_v2_accepts_canonical_json_with_unicode_and_empty_object(): void
    {
        $payload = [
            'status' => 'Approved',
            'name' => 'José',
            'url' => 'https://media.didit.me/a.jpg',
            'empty' => new \stdClass,
            'webhook_type' => 'status.updated',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $canonical = DiditWebhookSignature::canonicalizeJson($raw);
        $this->assertNotNull($canonical);
        $this->assertStringContainsString('José', $canonical);
        $this->assertStringContainsString('https://media.didit.me/a.jpg', $canonical);
        $this->assertStringContainsString('"empty":{}', $canonical);

        $secret = 'test-secret';
        $ok = DiditWebhookSignature::verify($raw, $secret, [
            'signature_v2' => hash_hmac('sha256', $canonical, $secret),
            'timestamp' => (string) time(),
        ]);

        $this->assertTrue($ok);
    }

    public function test_raw_hmac_of_body_does_not_satisfy_v2_when_keys_are_unsorted(): void
    {
        $raw = '{"status":"Approved","application_id":"app"}';
        $secret = 'test-secret';

        $ok = DiditWebhookSignature::verify($raw, $secret, [
            'signature_v2' => hash_hmac('sha256', $raw, $secret),
            'timestamp' => (string) time(),
        ]);

        $this->assertFalse($ok);
    }

    public function test_simple_signature_uses_envelope_fields(): void
    {
        $payload = [
            'timestamp' => 1789207165,
            'session_id' => '15af606c-151d-4e7a-a903-0d4483880990',
            'status' => 'Approved',
            'webhook_type' => 'status.updated',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $simple = hash_hmac(
            'sha256',
            '1789207165:15af606c-151d-4e7a-a903-0d4483880990:Approved:status.updated',
            'test-secret',
        );

        $ok = DiditWebhookSignature::verify($raw, 'test-secret', [
            'signature_v2' => str_repeat('c', 64),
            'signature_simple' => $simple,
            'timestamp' => (string) time(),
        ]);

        $this->assertTrue($ok);
    }
}
