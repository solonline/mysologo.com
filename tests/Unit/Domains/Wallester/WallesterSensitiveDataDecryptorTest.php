<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\WallesterCardRsaKeyService;
use App\Domains\Wallester\Services\WallesterRsaOaep;
use App\Domains\Wallester\Services\WallesterSensitiveDataDecryptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithWallesterSettings;
use Tests\TestCase;

class WallesterSensitiveDataDecryptorTest extends TestCase
{
    use InteractsWithWallesterSettings;
    use RefreshDatabase;

    public function test_throws_for_invalid_payload_encoding(): void
    {
        $this->seedWallesterSettings([
            'wallester.card_rsa_private_key' => $this->fixturePrivateKeyPem(),
        ]);

        $decryptor = new WallesterSensitiveDataDecryptor(app(WallesterCardRsaKeyService::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Wallester CardNumber payload encoding.');

        $decryptor->decryptCardNumber('not-valid-base64');
    }

    public function test_decrypts_card_number_payload_when_openssl_key_generation_is_available(): void
    {
        $keyPair = $this->generateKeyPair();

        if ($keyPair === null) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        [$privateKeyPem, $publicKeyPem] = $keyPair;
        $this->seedWallesterSettings([
            'wallester.card_rsa_private_key' => $privateKeyPem,
        ]);

        $payload = $this->wrapEncryptedPayload($publicKeyPem, '4302631234561795', 'CardNumber');
        $decryptor = new WallesterSensitiveDataDecryptor(app(WallesterCardRsaKeyService::class));

        $this->assertSame('4302631234561795', $decryptor->decryptCardNumber($payload));
    }

    public function test_decrypts_pin_payload_when_openssl_key_generation_is_available(): void
    {
        $keyPair = $this->generateKeyPair();

        if ($keyPair === null) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this environment.');
        }

        [$privateKeyPem, $publicKeyPem] = $keyPair;
        $this->seedWallesterSettings([
            'wallester.card_rsa_private_key' => $privateKeyPem,
        ]);

        $payload = $this->wrapEncryptedPayload($publicKeyPem, '4321', 'PIN');
        $decryptor = new WallesterSensitiveDataDecryptor(app(WallesterCardRsaKeyService::class));

        $this->assertSame('4321', $decryptor->decryptPin($payload));
    }

    private function fixturePrivateKeyPem(): string
    {
        return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAu1SU1L/JnW5yheG40UIKWzeR0tXs0/ug1gEFWhrt5tbv4rmU
DkGz7zCo55w5tt67/RfvJxSP7D3SpfSC9/PESFA8QBADSp17V9sVUyvbKiOphHq3
YISEqVKmyrIpnV546HZRbXbfg+QT08ycfYC94uu4tb6A+YpzNNFJ2BYL8TRhcQDM
tIvcqM3rmTSMb7tb9vFg7THb4Wq93rnJQx1/5e6Y8kDraIigwXwrxi7APRI22Wm
6kN7QNZ9K5K0G0PZJd9zR1aoZt4ligJSzne/Pexpt0tHLLVJbAUjXnM5+Y2Vwpu
NW1/j6kq+0xrnc9QBbg9Qnk+ZRHain8UwIDAQABAoIBADj1se2bkt0s7c09kkxt
0GDzQ9WzKYZhYJSC4Aw2P26pDi4T2YzM9Tdqv3MfZtKcX0bQM4Qg1zxw0aMEz7g
nKU8Qiy+OwaAu99BZ8xd2Fz3dthxpM4ck9A8TN+U7epC4zJtyJYH2bxdFfMQTjA
2BBfaiBxf23RcAcHYe2/Q98BNwG9FEjLm69c/GV6YNj9nqeiA8HtbZh658JKiX2z
7YZNvD7hRQOKW9PmR8cxywSVczGKN7l8YnwWalmnXUQN9n4jCBhoaaZ9ej9G1958
qKKlM94ZYkiq0ALdBcfwLPoYfzq0iTv5f3Fq8tWaiyt8IFNYfL5kY0n1rliygKn
VjQECgYEA7WN8+jYlyJ2BxtG3JMVdW/co9IEQVuynowd/3e6ogKeMuRmPHdKAzlS
vMpdLVnBAJYZUnMEH4KhC75NhmfKx7+YUXiucU8/4Uhp6U9qv8mD6h8ErjNmy2iN
NaGSfyIJv59yH1+pwOkMWZL7+Lj1tkjQW7R/VXgW/s5czKp8CgYEAy0jaX8kfk8m
tpsIhrI0Kgg5Z0UZWiYAf43CuLWLofbUZb1izwJ/+Yw0d5h42OkqdcC2oBu6B26x
d+g6RxIU/A5KBvkZFYEcKYAZ/tsaev+yndUZddkaXkVZVJdfXlnNHqDf1JFbD8/8
zspKJ5Jll/q8pzMbbxWU0VT7vQQgYQKBgQCH4VctaPXuWd73sYlxfWqk98HYjqU
o/JOm8k6b5fmJMKiXeSoIFZ4quBOujpTo1WF+uO1vK5FA/L7cEN/THBAPkgBCfU/
mnl16uzXRHKZtYwQa8c9JV6eXTMpzM0O6vUI1FjBwBk+TjWHt86CX4y5jwGrpLK
vjxTAhnvxb7QIDAQAB
-----END RSA PRIVATE KEY-----
PEM;
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

        return [$privateKeyPem, $details['key']];
    }

    private function wrapEncryptedPayload(string $publicKeyPem, string $plaintext, string $label): string
    {
        $encrypted = WallesterRsaOaep::encrypt($plaintext, $publicKeyPem, $label);

        return "-----BEGIN {$label} MESSAGE-----\n"
            .base64_encode($encrypted)
            ."\n-----END {$label} MESSAGE-----";
    }
}
