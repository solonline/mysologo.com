<?php
namespace App\Providers;

use App\Clients\StripeClient;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;

class StripeProvider
{
    public function __construct(protected StripeClient $client) {}

    public function createCustomer(array $customer): array
    {
        return $this->run('createCustomer', fn () => $this->client->createCustomer([
            'name'  => $customer['name']  ?? null,
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ]));
    }

    public function getCustomer(string $customerId): array
    {
        return $this->run('getCustomer', fn () => $this->client->getCustomer($customerId));
    }

    public function createPaymentIntent(array $data): array
    {
        return $this->run('createPaymentIntent', fn () => $this->client->createPaymentIntent($data));
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        return $this->run('attachPaymentMethod', fn () =>
            $this->client->attachPaymentMethod($paymentMethodId, $customerId)
        );
    }

    public function createSubscription(array $data): array
    {
        return $this->run('createSubscription', fn () => $this->client->createSubscription($data));
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->run('cancelSubscription', fn () => $this->client->cancelSubscription($subscriptionId));
    }

    public function getCashBalance(string $customerId): array
    {
        return $this->run('getCashBalance', fn () => $this->client->getCashBalance($customerId));
    }

    public function createCustomerFundingInstructions(string $customerId, array $data): array
    {
        return $this->run('createCustomerFundingInstructions', fn () =>
            $this->client->createCustomerFundingInstructions($customerId, $data)
        );
    }

    public function getCustomerFundingInstructions(string $customerId): array
    {
        return $this->run('getCustomerFundingInstructions', fn () =>
            $this->client->getCustomerFundingInstructions($customerId)
        );
    }

    public function getBalanceTransaction(string $balanceTransactionId): array
    {
        return $this->run('getBalanceTransaction', fn () =>
            $this->client->getBalanceTransaction($balanceTransactionId)
        );
    }

    // ── Private helpers ───────────────────────────────────

    private function run(string $operation, callable $fn): array
    {
        try {
            return $this->successResponse($fn());
        } catch (CardException $e) {
            return $this->handleStripeException($operation, $e, $e->getError()?->message ?? $e->getMessage());
        } catch (RateLimitException $e) {
            return $this->handleStripeException($operation, $e, 'Limite de requisições Stripe atingido.');
        } catch (InvalidRequestException $e) {
            return $this->handleStripeException($operation, $e, $e->getMessage());
        } catch (AuthenticationException $e) {
            return $this->handleStripeException($operation, $e, 'Erro de autenticação com o Stripe.');
        } catch (ApiConnectionException $e) {
            return $this->handleStripeException($operation, $e, 'Não foi possível conectar ao Stripe.');
        } catch (ApiErrorException $e) {
            return $this->handleStripeException($operation, $e, $e->getMessage());
        } catch (\Throwable $e) {
            \Log::channel('stripe')->error("stripe_unexpected: {$operation}", [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
            return $this->errorResponse('Erro inesperado na comunicação com o Stripe.');
        }
    }

    private function handleStripeException(string $operation, ApiErrorException $e, string $userMessage): array
    {
        $error = $e->getError();

        \Log::channel('stripe')->error("stripe_error: {$operation}", \array_filter([
            'http_status'  => $e->getHttpStatus(),
            'request_id'   => $e->getRequestId(),
            'type'         => $error?->type,
            'code'         => $error?->code,
            'decline_code' => $error?->decline_code,
            'param'        => $error?->param,
            'message'      => $e->getMessage(),
        ]));

        return $this->errorResponse($userMessage);
    }

    protected function successResponse(array $data): array
    {
        return ['status' => 'success', 'message' => null, 'data' => $data];
    }

    protected function errorResponse(string $message): array
    {
        return ['status' => 'failed', 'message' => $message, 'data' => null];
    }
}
