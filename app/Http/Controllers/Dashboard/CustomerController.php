<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'doc_status', 'type']);

        $customers = $this->customerService->paginate($filters)->withQueryString();
        $stats     = $this->customerService->getStats();

        $customerStatuses = CustomerStatus::cases();
        $documentStatuses = DocumentStatus::cases();

        return view('dashboard.customers.index', compact(
            'customers',
            'stats',
            'customerStatuses',
            'documentStatuses',
        ));
    }

    public function show(Customer $customer)
    {
        $customer          = $this->customerService->loadForDashboard($customer);
        $customerStatuses  = CustomerStatus::cases();
        $documentStatuses  = DocumentStatus::cases();

        return view('dashboard.customers.show', compact('customer', 'customerStatuses', 'documentStatuses'));
    }

    public function update(Request $request, Customer $customer)
    {
        $statusValues  = array_column(CustomerStatus::cases(), 'value');
        $docStatValues = array_column(DocumentStatus::cases(), 'value');

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:200'],
            'email'      => ['required', 'email', 'max:100', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone'      => ['nullable', 'string', 'max:20'],
            'currency'   => ['nullable', 'string', 'max:3'],
            'language'   => ['nullable', 'string', 'max:5'],
            'status'     => ['required', 'string', Rule::in($statusValues)],
            'doc_status' => ['nullable', 'string', Rule::in($docStatValues)],
        ]);

        $customer->update($data);

        return back()->with('success', 'Cliente atualizado com sucesso.');
    }
}
