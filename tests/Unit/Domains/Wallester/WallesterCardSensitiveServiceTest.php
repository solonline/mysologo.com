<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Wallester\Contracts\WallesterGatewayInterface;
use App\Domains\Wallester\Services\WallesterCardRsaKeyService;
use App\Domains\Wallester\Services\WallesterCardSensitiveService;
use App\Domains\Wallester\Services\WallesterSensitiveDataDecryptor;
use App\Models\CustomerWallesterCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class WallesterCardSensitiveServiceTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_reveal_for_customer_returns_decrypted_card_data(): void
    {
        Config::set('services.wallester.card_rsa_private_key', 'configured');

        $customer = $this->createRetailCustomer();
        $account = $this->createWallesterAccount($customer, IntegrationAccountStatus::ACTIVE, 'wal_sensitive');

        $card = CustomerWallesterCard::query()->create([
            'customer_wallester_account_id' => $account->id,
            'wallester_card_id' => 'card_sensitive_1',
            'type' => 'Virtual',
            'status' => 'Active',
            'name' => 'Travel Card',
        ]);

        $gateway = Mockery::mock(WallesterGatewayInterface::class);
        $gateway->shouldReceive('getCard')
            ->once()
            ->with('card_sensitive_1', (string) $customer->id)
            ->andReturn([
                'id' => 'card_sensitive_1',
                'expiry_date' => '2029-01-31T23:59:59Z',
            ]);
        $gateway->shouldReceive('getEncryptedCardNumber')
            ->once()
            ->with('card_sensitive_1', 'public-key-base64', (string) $customer->id)
            ->andReturn('encrypted-card-number');
        $gateway->shouldReceive('getEncryptedCvv2')
            ->once()
            ->with('card_sensitive_1', 'public-key-base64', (string) $customer->id)
            ->andReturn('encrypted-cvv');

        $rsaKeys = Mockery::mock(WallesterCardRsaKeyService::class);
        $rsaKeys->shouldReceive('publicKeyBase64')->once()->andReturn('public-key-base64');

        $decryptor = Mockery::mock(WallesterSensitiveDataDecryptor::class);
        $decryptor->shouldReceive('decryptCardNumber')
            ->once()
            ->with('encrypted-card-number')
            ->andReturn('4302631234561795');
        $decryptor->shouldReceive('decryptCvv2')
            ->once()
            ->with('encrypted-cvv')
            ->andReturn('987');

        $deviceAuth = Mockery::mock(DeviceAuthService::class);
        $deviceAuth->shouldNotReceive('verifyPin');

        $service = new WallesterCardSensitiveService($gateway, $rsaKeys, $decryptor, $deviceAuth);

        $result = $service->revealForCustomer($customer, $card, ['password' => 'password']);

        $this->assertSame('4302631234561795', $result['card_number']);
        $this->assertSame('987', $result['cvv']);
        $this->assertSame('2029-01-31T23:59:59Z', $result['expiry_date']);
    }
}
