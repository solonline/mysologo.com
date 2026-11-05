<?php

namespace App\Domains\Stripe\Clients;

use App\Domains\Settings\Services\SettingsService;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient as Stripe;
use Stripe\Webhook;

class StripeHttpClient
{
    /** @var list<string> */
    private const CURRENCIES = ['eur', 'usd', 'gbp', 'chf'];

    protected ?string $secretKey = null;

    protected ?string $activeSettingKey = null;

    protected ?Stripe $stripe = null;

    public function __construct(protected SettingsService $settings)
    {
        // Keys are resolved lazily to avoid DB queries during console bootstrap
    }

    public function forCurrency(string $currency): static
    {
        return $this->useSecret('stripe.'.strtolower($currency).'.secret_key');
    }

    public function forMode(string $mode, string $currency): static
    {
        $currency = strtolower($currency);
        $field = $mode === 'test' ? 'test_secret_key' : 'secret_key';

        return $this->useSecret("stripe.{$currency}.{$field}");
    }

    public function publishableKey(string $mode, string $currency): ?string
    {
        $currency = strtolower($currency);
        $field = $mode === 'test' ? 'test_public_key' : 'public_key';
        $key = $this->settings->getByKey("stripe.{$currency}.{$field}");

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function useSecret(string $settingKey): static
    {
        $key = $this->settings->getByKey($settingKey);
        $resolved = is_string($key) && $key !== '' ? $key : null;

        if ($settingKey !== $this->activeSettingKey || $resolved !== $this->secretKey) {
            $this->activeSettingKey = $settingKey;
            $this->secretKey = $resolved;
            $this->stripe = null;
        }

        return $this;
    }

    private function client(): Stripe
    {
        if ($this->stripe === null) {
            if ($this->activeSettingKey === null) {
                $this->useSecret('stripe.eur.secret_key');
            }
            if (empty($this->secretKey)) {
                $which = $this->activeSettingKey ?? 'stripe.eur.secret_key';
                throw new \RuntimeException("Stripe is not configured: {$which} is missing.");
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

    public function getCharge(string $chargeId): array
    {
        return $this->client()->charges->retrieve($chargeId)->toArray();
    }

    public function applyCustomerBalance(string $paymentIntentId, array $params = []): array
    {
        return $this->client()->paymentIntents->applyCustomerBalance($paymentIntentId, $params)->toArray();
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
        $secrets = $this->webhookSecrets();
        if ($secrets === []) {
            throw new \RuntimeException('Stripe is not configured: stripe webhook secret is missing.');
        }

        $last = null;
        foreach ($secrets as $secret) {
            try {
                return Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (SignatureVerificationException $e) {
                $last = $e;
            }
        }

        throw $last ?? new \RuntimeException('Stripe webhook signature verification failed.');
    }

    /** @return list<string> */
    private function webhookSecrets(): array
    {
        $secrets = [];

        foreach (self::CURRENCIES as $currency) {
            foreach (['webhook_secret', 'test_webhook_secret'] as $field) {
                $value = $this->settings->getByKey("stripe.{$currency}.{$field}");
                if (is_string($value) && $value !== '') {
                    $secrets[$value] = $value;
                }
            }
        }

        return array_values($secrets);
    }

    public function getTransactionsByIdPayout(string $payoutId): array
    {
        $all = [];
        $startingAfter = null;

        do {
            $params = ['payout' => $payoutId, 'limit' => 100];
            if ($startingAfter !== null) {
                $params['starting_after'] = $startingAfter;
            }

            $page = $this->client()->balanceTransactions->all($params)->toArray();
            $data = $page['data'] ?? [];
            $all = array_merge($all, $data);
            $hasMore = (bool) ($page['has_more'] ?? false);
            $startingAfter = $hasMore && $data !== []
                ? (string) $data[array_key_last($data)]['id']
                : null;
        } while ($hasMore && $startingAfter !== null);

        return [
            'object' => 'list',
            'data' => $all,
            'has_more' => false,
            'url' => '/v1/balance_transactions',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    public function listPayouts(array $params): array
    {
        return $this->paginateList(
            fn (array $pageParams) => $this->client()->payouts->all($pageParams)->toArray(),
            $params,
            '/v1/payouts',
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    public function listEvents(array $params): array
    {
        return $this->paginateList(
            fn (array $pageParams) => $this->client()->events->all($pageParams)->toArray(),
            $params,
            '/v1/events',
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    public function listPaymentIntents(array $params): array
    {
        return $this->paginateList(
            fn (array $pageParams) => $this->client()->paymentIntents->all($pageParams)->toArray(),
            $params,
            '/v1/payment_intents',
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    public function listCharges(array $params): array
    {
        return $this->paginateList(
            fn (array $pageParams) => $this->client()->charges->all($pageParams)->toArray(),
            $params,
            '/v1/charges',
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    public function listCashBalanceTransactions(string $stripeCustomerId, array $params = []): array
    {
        return $this->paginateList(
            fn (array $pageParams) => $this->client()->customers->allCashBalanceTransactions($stripeCustomerId, $pageParams)->toArray(),
            $params,
            '/v1/customers/'.$stripeCustomerId.'/cash_balance_transactions',
        );
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $fetcher
     * @param  array<string, mixed>  $params
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, url: string}
     */
    private function paginateList(callable $fetcher, array $params, string $url): array
    {
        $all = [];
        $startingAfter = null;
        $pages = 0;

        do {
            $pageParams = array_merge($params, ['limit' => 100]);
            if ($startingAfter !== null) {
                $pageParams['starting_after'] = $startingAfter;
            }

            $page = $fetcher($pageParams);
            $data = $page['data'] ?? [];
            $all = array_merge($all, $data);
            $hasMore = (bool) ($page['has_more'] ?? false);
            $startingAfter = $hasMore && $data !== []
                ? (string) $data[array_key_last($data)]['id']
                : null;
            $pages++;
        } while ($hasMore && $startingAfter !== null && $pages < 50);

        return [
            'object' => 'list',
            'data' => $all,
            'has_more' => false,
            'url' => $url,
        ];
    }
}
