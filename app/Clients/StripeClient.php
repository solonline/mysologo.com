<?php
namespace App\Clients;

use App\Services\SettingsService;
use Stripe\Event;
use Stripe\StripeClient as Stripe;
use Stripe\Webhook;

class StripeClient
{
    protected string $secretKey;
    protected string $webhookSecret;
    protected Stripe $stripe;

    public function __construct(protected SettingsService $settings)
    {
        $this->secretKey     = $this->settings->getByKey('stripe.secret_key');
        $this->webhookSecret = $this->settings->getByKey('stripe.webhook_secret');
        $this->stripe        = new Stripe($this->secretKey);
    }

    public function createCustomer(array $data): array
    {
        return $this->stripe->customers->create($data)->toArray();
    }

    public function getCustomer(string $customerId): array
    {
        return $this->stripe->customers->retrieve($customerId)->toArray();
    }

    public function createPaymentIntent(array $data): array
    {
        return $this->stripe->paymentIntents->create($data)->toArray();
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId)->toArray();
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return $this->stripe->paymentMethods->attach($paymentMethodId, ['customer' => $customerId])->toArray();
    }

    public function createSubscription(array $data): array
    {
        return $this->stripe->subscriptions->create($data)->toArray();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->stripe->subscriptions->cancel($subscriptionId)->toArray();
    }

    public function createCustomerFundingInstructions(string $customerId, array $data): array
    {
        return $this->stripe->request('post', "/v1/customers/{$customerId}/funding_instructions", $data, [
            'headers' => ['Stripe-Version' => '2026-04-22.preview'],
        ])->toArray();
    }

    public function getCustomerFundingInstructions(string $customerId): array
    {
        return $this->stripe->request('get', "/v1/customers/{$customerId}/funding_instructions", [], [
            'headers' => ['Stripe-Version' => '2026-04-22.preview'],
        ])->toArray();
    }

    public function getCashBalance(string $customerId): array
    {
        return $this->stripe->customers->retrieveCashBalance($customerId)->toArray();
    }

    public function getBalanceTransaction(string $balanceTransactionId): array
    {
        return $this->stripe->balanceTransactions->retrieve($balanceTransactionId)->toArray();
    }

    public function listPaymentMethods(string $type = 'card', ?string $customerId = null): array
    {
        return $this->stripe->paymentMethods->all(['customer' => $customerId, 'type' => $type])->toArray();
    }

    public function getPaymentMethodAvailability(): array
    {
        $configs = $this->stripe->paymentMethodConfigurations->all(['limit' => 1]);

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
        if (empty($this->webhookSecret)) {
            throw new \RuntimeException('Stripe is not configured: stripe_webhook_secret is missing.');
        }

        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }
}
