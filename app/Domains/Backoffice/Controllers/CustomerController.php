<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CancelCustomerBalanceAdjustmentAction;
use App\Domains\Backoffice\Actions\ConfirmCustomerBalanceAdjustmentAction;
use App\Domains\Backoffice\Actions\RequestCustomerBalanceAdjustmentAction;
use App\Domains\Backoffice\Actions\ResendCustomerBalanceAdjustmentOtpAction;
use App\Domains\Backoffice\Actions\RetryCustomerProvisioningAction;
use App\Domains\Backoffice\Actions\RetryStripeFundingAction;
use App\Domains\Backoffice\Actions\UpdateCustomerFromBackofficeAction;
use App\Domains\Backoffice\DTOs\UpdateCustomerFromBackofficeData;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use App\Domains\Backoffice\Queries\GetCustomerActivityTimelineQuery;
use App\Domains\Backoffice\Queries\GetCustomerDetailQuery;
use App\Domains\Backoffice\Queries\GetRecentCustomerMovementsQuery;
use App\Domains\Backoffice\Queries\ListCustomersQuery;
use App\Domains\Backoffice\Requests\ConfirmCustomerBalanceAdjustmentRequest;
use App\Domains\Backoffice\Requests\RequestCustomerBalanceAdjustmentRequest;
use App\Domains\Backoffice\Requests\UpdateCustomerRequest;
use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ChangeCustomerPhoneRequest;
use App\Http\Requests\Customer\ConfirmPendingCustomerPhoneRequest;
use App\Models\Customer;
use App\Models\CustomerBalanceAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        protected ListCustomersQuery $listCustomers,
        protected GetCustomerDetailQuery $customerDetail,
        protected GetCustomerActivityTimelineQuery $customerActivityTimeline,
        protected GetRecentCustomerMovementsQuery $recentCustomerMovements,
        protected UpdateCustomerFromBackofficeAction $updateCustomer,
        protected RetryCustomerProvisioningAction $retryProvisioning,
        protected RetryStripeFundingAction $retryStripeFunding,
        protected CustomerPhoneChangeService $phoneChange,
        protected RequestCustomerBalanceAdjustmentAction $requestBalanceAdjustment,
        protected ConfirmCustomerBalanceAdjustmentAction $confirmBalanceAdjustment,
        protected ResendCustomerBalanceAdjustmentOtpAction $resendBalanceAdjustmentOtp,
        protected CancelCustomerBalanceAdjustmentAction $cancelBalanceAdjustment,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'doc_status', 'type']);
        $result = $this->listCustomers->execute($filters);

        return view('dashboard.customers.index', [
            'customers' => $result->customers->withQueryString(),
            'stats' => $result->stats,
            'customerStatuses' => CustomerLifecycleStatus::cases(),
            'documentStatuses' => DocumentStatus::cases(),
        ]);
    }

    public function show(Customer $customer)
    {
        $customer = $this->customerDetail->execute($customer);

        $pendingBalanceAdjustment = CustomerBalanceAdjustment::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerBalanceAdjustmentStatus::PendingApproval)
            ->with('approvals')
            ->latest('id')
            ->first();

        return view('dashboard.customers.show', [
            'customer' => $customer,
            'activityTimeline' => $this->customerActivityTimeline->execute($customer, 10),
            'customerStatuses' => CustomerLifecycleStatus::cases(),
            'documentStatuses' => DocumentStatus::cases(),
            'currentPhoneVerified' => $this->phoneChange->isCurrentPhoneVerified($customer),
            'pendingPhoneChange' => $this->phoneChange->pendingChangePhone($customer),
            'pendingBalanceAdjustment' => $pendingBalanceAdjustment,
            'currentPhoneVerifiedAt' => $customer->phone
                ? $customer->phoneVerifications()
                    ->where('phone', preg_replace('/\D+/', '', (string) $customer->phone) ?: $customer->phone)
                    ->whereNotNull('verified_at')
                    ->latest('verified_at')
                    ->value('verified_at')
                : null,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->updateCustomer->execute(
            $customer,
            UpdateCustomerFromBackofficeData::fromValidated($request->validated()),
        );

        return back()->with('success', 'Cliente atualizado com sucesso.');
    }

    public function requestPhoneChange(ChangeCustomerPhoneRequest $request, Customer $customer)
    {
        try {
            $result = $this->phoneChange->requestChange($customer, $request->validated('phone'));
        } catch (ApiException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $message = $result['status'] === 'pending_verification'
            ? 'Código SMS enviado para o novo número. Confirme abaixo.'
            : 'Telefone atualizado com sucesso.';

        return back()->with('success', $message);
    }

    public function resendPhoneChange(Customer $customer)
    {
        try {
            $result = $this->phoneChange->resendPendingChange($customer);
        } catch (ApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Código SMS reenviado para '.$result['phone'].'.');
    }

    public function cancelPhoneChange(Customer $customer)
    {
        try {
            $this->phoneChange->cancelPendingChange($customer);
        } catch (ApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Alteração de telefone pendente cancelada.');
    }

    public function confirmPhoneChange(ConfirmPendingCustomerPhoneRequest $request, Customer $customer)
    {
        try {
            $this->phoneChange->confirmPendingChange(
                $customer,
                $request->validated('code'),
            );
        } catch (ApiException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Telefone confirmado e atualizado com sucesso.');
    }

    public function retryProvisioning(Customer $customer)
    {
        $this->retryProvisioning->execute($customer);

        return back()->with('success', 'Provisioning reenviado para a fila.');
    }

    public function retryStripeFunding(Customer $customer)
    {
        $customer = $this->retryStripeFunding->execute($customer);

        $message = $customer->stripeAccount?->funding_status === StripeFundingStatus::ACTIVE
            ? 'IBAN de funding criado com sucesso.'
            : 'Não foi possível criar o IBAN de funding.';

        return back()->with(
            $customer->stripeAccount?->funding_status === StripeFundingStatus::ACTIVE ? 'success' : 'error',
            $message,
        );
    }

    public function requestBalanceAdjustment(RequestCustomerBalanceAdjustmentRequest $request, Customer $customer)
    {
        try {
            $adjustment = $this->requestBalanceAdjustment->execute(
                customer: $customer,
                actor: $request->user(),
                direction: $request->direction(),
                amountCents: $request->amountCents(),
                description: $request->validated('description'),
            );
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                return $this->validationErrorResponse($exception);
            }

            return back()->withInput()->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
        } catch (ApiException $exception) {
            if ($this->wantsJson($request)) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

        $label = $adjustment->direction->label();

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => "Código SMS enviado. Confirme o {$label} abaixo.",
                'adjustment' => $this->serializeBalanceAdjustment($adjustment),
            ]);
        }

        return back()->with('success', "Código SMS enviado. Confirme o {$label} abaixo.");
    }

    public function confirmBalanceAdjustment(
        ConfirmCustomerBalanceAdjustmentRequest $request,
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
    ) {
        try {
            $adjustment = $this->confirmBalanceAdjustment->execute(
                customer: $customer,
                adjustment: $adjustment,
                actor: $request->user(),
                code: $request->validated('code'),
            );
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                return $this->validationErrorResponse($exception);
            }

            return back()->withInput()->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
        } catch (ApiException $exception) {
            if ($this->wantsJson($request)) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            if ($this->wantsJson($request)) {
                return $this->errorResponse($exception->getMessage(), 500);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($adjustment->status === CustomerBalanceAdjustmentStatus::Executed) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $adjustment->direction->label().' executado com sucesso.',
                    'adjustment' => $this->serializeBalanceAdjustment($adjustment),
                ]);
            }

            return back()->with('success', $adjustment->direction->label().' executado com sucesso.');
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Código validado. Aguarda aprovações adicionais.',
                'adjustment' => $this->serializeBalanceAdjustment($adjustment),
            ]);
        }

        return back()->with('success', 'Código validado. Aguarda aprovações adicionais.');
    }

    public function resendBalanceAdjustment(
        Request $request,
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
    ) {
        if ($request->user()?->hasRole('super-admin', 'web') !== true) {
            abort(403);
        }

        try {
            $this->resendBalanceAdjustmentOtp->execute($customer, $adjustment, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                return $this->validationErrorResponse($exception);
            }

            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Código SMS reenviado.',
                'adjustment' => $this->serializeBalanceAdjustment($adjustment->fresh(['approvals'])),
            ]);
        }

        return back()->with('success', 'Código SMS reenviado.');
    }

    public function cancelBalanceAdjustment(
        Request $request,
        Customer $customer,
        CustomerBalanceAdjustment $adjustment,
    ) {
        if ($request->user()?->hasRole('super-admin', 'web') !== true) {
            abort(403);
        }

        try {
            $adjustment = $this->cancelBalanceAdjustment->execute($customer, $adjustment, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                return $this->validationErrorResponse($exception);
            }

            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Ajuste pendente cancelado.',
                'adjustment' => $this->serializeBalanceAdjustment($adjustment),
            ]);
        }

        return back()->with('success', 'Ajuste pendente cancelado.');
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    private function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => collect($exception->errors())->flatten()->first(),
            'errors' => $exception->errors(),
        ], 422);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    private function serializeBalanceAdjustment(?CustomerBalanceAdjustment $adjustment): ?array
    {
        if (! $adjustment instanceof CustomerBalanceAdjustment) {
            return null;
        }

        $adjustment->loadMissing('approvals');

        return [
            'id' => $adjustment->id,
            'direction' => $adjustment->direction->value,
            'direction_label' => $adjustment->direction->label(),
            'amount_cents' => $adjustment->amount_cents,
            'amount_formatted' => number_format($adjustment->amount_cents / 100, 2, ',', '.'),
            'currency' => $adjustment->currency,
            'description' => $adjustment->description,
            'status' => $adjustment->status->value,
            'required_approvals' => $adjustment->required_approvals,
            'verified_approvals' => $adjustment->approvals->whereNotNull('verified_at')->count(),
        ];
    }
}
