<?php

namespace App\Domains\Stripe\Contracts;

interface StripeGatewayInterface
{
    public function createCustomer(array $customer): array;

    public function getCustomer(string $customerId): array;

    public function createPaymentIntent(array $data): array;

    public function getPaymentIntent(string $paymentIntentId, string $currency = 'eur'): array;

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array;

    public function createSubscription(array $data): array;

    public function cancelSubscription(string $subscriptionId): array;

    public function getCashBalance(string $customerId): array;

    public function createCustomerFundingInstructions(string $customerId, array $data): array;

    public function getCustomerFundingInstructions(string $customerId): array;

    public function getBalanceTransaction(string $balanceTransactionId): array;

    public function getTransactionsByIdPayout(string $payoutId, string $currency = 'eur', string $mode = 'live'): array;

    public function createPaymentIntentWithMode(array $data, string $mode): array;

    public function getPaymentIntentWithMode(string $paymentIntentId, string $mode, string $currency = 'eur'): array;

    public function capturePaymentIntent(string $paymentIntentId): array;

    public function createTerminalConnectionToken(string $currency = 'eur'): array;

    public function applyCustomerBalance(string $paymentIntentId, array $params = []): array;
}
