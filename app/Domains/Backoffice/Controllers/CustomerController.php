<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\RetryCustomerProvisioningAction;
use App\Domains\Backoffice\Actions\UpdateCustomerFromBackofficeAction;
use App\Domains\Backoffice\DTOs\UpdateCustomerFromBackofficeData;
use App\Domains\Backoffice\Queries\GetCustomerDetailQuery;
use App\Domains\Backoffice\Queries\ListCustomersQuery;
use App\Domains\Backoffice\Requests\UpdateCustomerRequest;
use App\Domains\Kyc\Enums\DocumentStatus;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected ListCustomersQuery $listCustomers,
        protected GetCustomerDetailQuery $customerDetail,
        protected UpdateCustomerFromBackofficeAction $updateCustomer,
        protected RetryCustomerProvisioningAction $retryProvisioning,
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
        return view('dashboard.customers.show', [
            'customer' => $this->customerDetail->execute($customer),
            'customerStatuses' => CustomerLifecycleStatus::cases(),
            'documentStatuses' => DocumentStatus::cases(),
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

    public function retryProvisioning(Customer $customer)
    {
        $this->retryProvisioning->execute($customer);

        return back()->with('success', 'Provisioning reenviado para a fila.');
    }
}
