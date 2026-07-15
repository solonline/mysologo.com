<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\RetryCustomerProvisioningAction;
use App\Domains\Backoffice\Actions\RetryStripeFundingAction;
use App\Domains\Backoffice\Actions\UpdateCustomerFromBackofficeAction;
use App\Domains\Backoffice\DTOs\UpdateCustomerFromBackofficeData;
use App\Domains\Backoffice\Queries\GetCustomerActivityTimelineQuery;
use App\Domains\Backoffice\Queries\GetCustomerDetailQuery;
use App\Domains\Backoffice\Queries\ListCustomersQuery;
use App\Domains\Backoffice\Requests\UpdateCustomerRequest;
use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ChangeCustomerPhoneRequest;
use App\Http\Requests\Customer\ConfirmPendingCustomerPhoneRequest;
use App\Http\Requests\Customer\ConfirmCustomerPhoneRequest;
use App\Models\Customer;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected ListCustomersQuery $listCustomers,
        protected GetCustomerDetailQuery $customerDetail,
        protected GetCustomerActivityTimelineQuery $customerActivityTimeline,
        protected UpdateCustomerFromBackofficeAction $updateCustomer,
        protected RetryCustomerProvisioningAction $retryProvisioning,
        protected RetryStripeFundingAction $retryStripeFunding,
        protected CustomerPhoneChangeService $phoneChange,
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

        return view('dashboard.customers.show', [
            'customer' => $customer,
            'activityTimeline' => $this->customerActivityTimeline->execute($customer),
            'customerStatuses' => CustomerLifecycleStatus::cases(),
            'documentStatuses' => DocumentStatus::cases(),
            'currentPhoneVerified' => $this->phoneChange->isCurrentPhoneVerified($customer),
            'pendingPhoneChange' => $this->phoneChange->pendingChangePhone($customer),
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
}
