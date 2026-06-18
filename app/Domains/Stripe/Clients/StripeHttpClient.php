<?php
namespace App\Domains\Stripe\Clients;

use App\Domains\Settings\Services\SettingsService;
use Stripe\Event;
use Stripe\StripeClient as Stripe;
use Stripe\Webhook;

class StripeHttpClient
{
    protected ?string $secretKey     = null;
    protected ?string $webhookSecret = null;
    protected ?Stripe $stripe        = null;

    public function __construct(protected SettingsService $settings)
    {
        // Keys are resolved lazily to avoid DB queries during console bootstrap
    }

    public function forCurrency(string $currency): static
    {
        $key = $this->settings->getByKey('stripe.' . strtolower($currency) . '.secret_key');
        if (!empty($key) && $key !== $this->secretKey) {
            $this->secretKey = $key;
            $this->stripe    = null;
        }

        return $this;
    }

    public function forMode(string $mode, string $currency): static
    {
        $settingKey = $mode === 'test'
            ? 'stripe.' . strtolower($currency) . '.test_secret_key'
            : 'stripe.' . strtolower($currency) . '.secret_key';

        $key = $this->settings->getByKey($settingKey);
        if (!empty($key) && $key !== $this->secretKey) {
            $this->secretKey = $key;
            $this->stripe    = null;
        }

        return $this;
    }

    private function client(): Stripe
    {
        if ($this->stripe === null) {
            if ($this->secretKey === null) {
                $this->secretKey = $this->settings->getByKey('stripe.eur.secret_key');
            }
            if (empty($this->secretKey)) {
                throw new \RuntimeException('Stripe is not configured: stripe_secret_key is missing.');
            }
            $this->stripe = new Stripe($this->secretKey);
        }

        return $this->stripe;
    }

    public function createCustomer(array $data): array
    {
        return $this->client()->customers->create($data)->toArray();
    }

    public function getCustomer(string $customerId): array
    {
        return $this->client()->customers->retrieve($customerId)->toArray();
    }

    public function createPaymentIntent(array $data): array
    {
        return $this->client()->paymentIntents->create($data)->toArray();
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId)->toArray();
    }

    public function capturePaymentIntent(string $paymentIntentId): array
    {
        return $this->client()->paymentIntents->capture($paymentIntentId)->toArray();
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return $this->client()->paymentMethods->attach($paymentMethodId, ['customer' => $customerId])->toArray();
    }

    public function createSubscription(array $data): array
    {
        return $this->client()->subscriptions->create($data)->toArray();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->client()->subscriptions->cancel($subscriptionId)->toArray();
    }

    public function createCustomerFundingInstructions(string $customerId, array $data): array
    {
        return $this->client()->request('post', "/v1/customers/{$customerId}/funding_instructions", $data, [
            'headers' => ['Stripe-Version' => '2026-04-22.preview'],
        ])->toArray();
    }

    public function getCustomerFundingInstructions(string $customerId): array
    {
        return $this->client()->request('get', "/v1/customers/{$customerId}/funding_instructions", [], [
            'headers' => ['Stripe-Version' => '2026-04-22.preview'],
        ])->toArray();
    }

    public function getCashBalance(string $customerId): array
    {
        return $this->client()->customers->retrieveCashBalance($customerId)->toArray();
    }

    public function getBalanceTransaction(string $balanceTransactionId): array
    {
        return $this->client()->balanceTransactions->retrieve($balanceTransactionId)->toArray();
    }

    public function createTerminalConnectionToken(): array
    {
        return $this->client()->terminal->connectionTokens->create([])->toArray();
    }

    public function listPaymentMethods(string $type = 'card', ?string $customerId = null): array
    {
        return $this->client()->paymentMethods->all(['customer' => $customerId, 'type' => $type])->toArray();
    }

    public function getPaymentMethodAvailability(): array
    {
        $configs = $this->client()->paymentMethodConfigurations->all(['limit' => 1]);

        if (empty($configs->data)) {
            return [];
        }

        $availability = [];
        foreach ($configs->data[0]->toArray() as $key => $value) {
            if (is_array($value) && array_key_exists('available', $value)) {
                $availability[$key] = (bool) $value['available'];
            }
        }

        return $availability;
    }

    public function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        if ($this->webhookSecret === null) {
            $this->webhookSecret = $this->settings->getByKey('stripe.eur.webhook_secret');
        }
        if (empty($this->webhookSecret)) {
            throw new \RuntimeException('Stripe is not configured: stripe_webhook_secret is missing.');
        }

        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }
}
