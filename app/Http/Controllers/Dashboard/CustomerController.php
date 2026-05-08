<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

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
        $customer = $this->customerService->loadForDashboard($customer);

        return view('dashboard.customers.show', compact('customer'));
    }
}
