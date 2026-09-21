<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\WallesterRsaOaep;
use App\Domains\Wallester\Services\WallesterSensitiveDataEncryptor;
use App\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterSensitiveDataEncryptorTest extends TestCase
{
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    public function test_throws_when_rsa_public_key_is_missing(): void
    {
        $this->seedWallesterSettings();

        $encryptor = app(WallesterSensitiveDataEncryptor::class);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unable to encrypt Wallester card CVV.');

        $encryptor->encryptCvv2('123');
    }

    public function test_encrypts_cvv_as_base64_pem_message_with_empty_oaep_label(): void
    {
        $keyPair = $this->generateKeyPair();

        if ($keyPair === null) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        [$privateKeyPem, $publicKeyPem] = $keyPair;
        $this->seedWallesterSettings([
            'wallester.rsa_public_key' => $publicKeyPem,
        ]);

        $encrypted = app(WallesterSensitiveDataEncryptor::class)->encryptCvv2('123');
        $pem = base64_decode($encrypted, true);

        $this->assertNotFalse($pem);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $encrypted);
        $this->assertStringStartsWith("-----BEGIN MESSAGE-----\n", $pem);
        $this->assertStringEndsWith("-----END MESSAGE-----\n", $pem);
        $this->assertNotSame('123', $encrypted);

        $innerBase64 = str_replace([
            '-----BEGIN MESSAGE-----',
            '-----END MESSAGE-----',
            "\r",
            "\n",
        ], '', $pem);
        $ciphertext = base64_decode($innerBase64, true);

        $this->assertNotFalse($ciphertext);
        $this->assertSame('123', WallesterRsaOaep::decrypt($ciphertext, $privateKeyPem, ''));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function generateKeyPair(): ?array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            return null;
        }

        $privateKeyPem = '';
        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! is_string($details['key'] ?? null)) {
            return null;
        }

        return [$privateKeyPem, $details['key']];
    }
}
