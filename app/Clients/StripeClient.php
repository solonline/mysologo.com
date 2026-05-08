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
        $this->secretKey = $this->settings->getByKey('stripe.secret_key');
        $this->webhookSecret = $this->settings->getByKey('stripe.webhook_secret');
        $this->stripe = new Stripe($this->secretKey);
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
        $intent = $this->stripe->paymentIntents->create($data);
        return $intent->toArray();
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

    public function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        if (empty($this->webhookSecret)) {
            throw new \RuntimeException('Stripe is not configured: stripe_webhook_secret is missing.');
        }

        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }

        /**
     * Cria funding instructions para um customer usando o endpoint preview do Stripe
     * @see https://stripe.com/docs/api/previews/funding_instructions_create_customer
     */
    public function createCustomerFundingInstructions(string $customerId, array $data): array
    {
        $headers = [
            'Stripe-Version' => '2026-04-22.preview',
        ];
        $url = "/v1/customers/{$customerId}/funding_instructions";
        $response = $this->stripe->request('post', $url, $data, ['headers' => $headers]);
        return $response->toArray();
    }

    public function getCustomerFundingInstructions(string $customerId): array
    {
        $headers = [
            'Stripe-Version' => '2026-04-22.preview',
        ];
        $url = "/v1/customers/{$customerId}/funding_instructions";
        $response = $this->stripe->request('get', $url, [], ['headers' => $headers]);
        return $response->toArray();
    }

    /*
    *
    */
    public function getCashBalance(string $customerId): array
    {
        $response = $this->stripe->customers->retrieveCashBalance($customerId);
        return $response->toArray();
    }

}
