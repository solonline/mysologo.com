<?php

namespace App\Domains\Customer\Repositories;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\DTOs\CreateResponsibleDTO;
use App\Domains\Customer\DTOs\RegisterCustomerDTO;
use App\Domains\Customer\DTOs\UpdateCustomerDTO;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function createCustomer(RegisterCustomerDTO $dto): Customer
    {
        return Customer::create(array_filter([
            'email' => $dto->email,
            'password' => $dto->password,
            'type' => $dto->type,
            'name' => $dto->name,
            'username' => $dto->username,
            'phone' => $dto->phone,
            'status' => CustomerLifecycleStatus::PENDING_EMAIL_VERIFICATION,
        ], fn ($v) => ! is_null($v)));
    }

    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }

    public function findByUsername(string $username, array $filters = [], ?int $excludeId = null): ?Customer
    {
        return Customer::where('username', $username)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when(isset($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['doc_status']), fn ($q) => $q->where('doc_status', $filters['doc_status']))
            ->first();
    }

    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    public function firstResponsible(Customer $corporate): ?Customer
    {
        return $corporate->responsibles()->first();
    }

    public function save(Customer $customer): void
    {
        $customer->save();
    }

    public function updateAvatar(Customer $customer, string $path): Customer
    {
        $customer->update(['avatar' => $path]);

        return $customer->fresh();
    }

    public function updateCustomer(Customer $customer, UpdateCustomerDTO $dto): Customer
    {
        $data = array_filter([
            'name' => $dto->name,
            'username' => $dto->username,
            'avatar' => $dto->avatar,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'referal_email_code' => $dto->referalEmailCode,
            'password' => $dto->password ? Hash::make($dto->password) : null,
        ], fn ($v) => ! is_null($v));

        $customer->update($data);

        return $customer->fresh();
    }

    public function createResponsible(Customer $corporate, CreateResponsibleDTO $dto): Customer
    {
        return Customer::create([
            'username' => $corporate->username . '_responsible_' . uniqid(),
            'name' => $dto->name,
            'email' => $dto->email,
            'corporate_id' => $corporate->id,
            'type' => 'responsible',
        ]);
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()->with('documents')->where('type', '!=', 'platform');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhereHas('documents', function ($d) use ($search) {
                        $d->where('document_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($docStatus = $filters['doc_status'] ?? null) {
            $query->where('doc_status', $docStatus);
        }

        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getStats(): array
    {
        $base = Customer::where('type', '!=', 'platform');

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', CustomerLifecycleStatus::ACTIVE)->count(),
            'pending' => (clone $base)->whereIn('status', array_map(
                fn (CustomerLifecycleStatus $s) => $s->value,
                CustomerLifecycleStatus::pendingStatuses()
            ))->count(),
            'blocked' => (clone $base)->whereIn('status', [
                CustomerLifecycleStatus::BLOCKED->value,
                CustomerLifecycleStatus::IDENTITY_REJECTED->value,
                CustomerLifecycleStatus::EBURY_REJECTED->value,
            ])->count(),
        ];
    }

    public function loadRelations(Customer $customer, array $relations): Customer
    {
        return $customer->load($relations);
    }
}
