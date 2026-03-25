<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateResponsibleRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{

    public function __construct(protected CustomerService $customerService)
    {
    }
    /**
     * Me
     *
     * @authenticated
     */
    public function me(Request $request)
    {
        return CustomerResource::make($request->user())->response()->setStatusCode(200);
    }

    /**
     * Atualiza os dados do usuário autenticado
     *
     * @authenticated
     * @header Authorization Bearer token
     */
    public function update(UpdateUserRequest $request)
    {
        // Lógica para atualizar os dados do usuário autenticado
    }

    /**
     * Create Responsible
     *
     * @authenticated
     * @header Authorization Bearer token
     */
    public function createResponsible(CreateResponsibleRequest $request)
    {
        return $this->customerService->updateOrCreateResponsible($request->user(), $request->validated());
    }

    /**
     * Summary of submitCorporateDocuments
     * @param Request $request
     * @return void
     */
    public function submitCorporateDocuments(Request $request)
    {
        // Lógica para submissão de documentos corporativos
    }
}
