<?php
namespace App\Providers;

use App\Clients\StripeClient;
use App\Models\Customer;
use Stripe\Exception\ApiErrorException;

class StripeProvider
{
    protected StripeClient $client;

    public function __construct(StripeClient $client)
    {
        $this->client = $client;
    }

    public function createCustomer(array $customer): array
    {
        $payload = [
            'name' => $customer['name'] ?? null,
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ];
        try {
            return $this->successResponse($this->client->createCustomer($payload));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao criar customer no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'customer_id' => $customer['id'] ?? null,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao criar customer', [
                'exception' => $e,
                'customer_id' => $customer['id'] ?? null,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    public function getCustomer(string $customerId): array
    {
        try {
            return $this->successResponse($this->client->getCustomer($customerId));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao buscar customer no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao buscar customer', [
                'exception' => $e,
                'customer_id' => $customerId,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    public function createPaymentIntent(array $data): array
    {
        try {
            return $this->successResponse($this->client->createPaymentIntent($data));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao criar payment intent no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao criar payment intent', [
                'exception' => $e,
                'data' => $data,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        try {
            return $this->successResponse($this->client->attachPaymentMethod($paymentMethodId, $customerId));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao anexar payment method no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao anexar payment method', [
                'exception' => $e,
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    public function createSubscription(array $data): array
    {
        try {
            return $this->successResponse($this->client->createSubscription($data));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao criar subscription no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao criar subscription', [
                'exception' => $e,
                'data' => $data,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            return $this->successResponse($this->client->cancelSubscription($subscriptionId));
        } catch (ApiErrorException $e) {
            \Log::warning('Falha ao cancelar subscription no Stripe', [
                'status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);
            return $this->errorResponse($e->getMessage());
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Stripe ao cancelar subscription', [
                'exception' => $e,
                'subscription_id' => $subscriptionId,
            ]);
            return $this->errorResponse('Erro de comunicação com Stripe.');
        }
    }

    protected function successResponse(array $data): array
    {
        return [
            'status' => 'success',
            'message' => null,
            'data' => $data,
        ];
    }

    protected function errorResponse(string $message): array
    {
        return [
            'status' => 'failed',
            'message' => $message,
            'data' => null,
        ];
    }
}
