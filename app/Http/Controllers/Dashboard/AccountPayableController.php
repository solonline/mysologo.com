<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\AccountPayableAttachment;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountPayableController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    public function index(Request $request): View
    {
        $query = AccountPayable::query()->withCount('attachments');

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search): void {
                $query->where('supplier', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($supplier = $request->input('supplier')) {
            $query->where('supplier', 'like', "%{$supplier}%");
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($currency = $request->input('currency')) {
            $query->where('currency', strtoupper($currency));
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($dueFrom = $request->input('due_from')) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }

        if ($dueTo = $request->input('due_to')) {
            $query->whereDate('due_date', '<=', $dueTo);
        }

        $accounts = $query
            ->orderByRaw("case when status = 'pending' then 0 when status = 'paid' then 1 else 2 end")
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.accounts-payable.index', [
            'accounts' => $accounts,
            'categories' => AccountPayable::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'currencies' => $this->acceptedCurrencies(),
            'statuses' => AccountPayable::statuses(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.accounts-payable.create', [
            'account' => null,
            'currencies' => $this->acceptedCurrencies(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $accounts = DB::transaction(fn () => AccountPayable::createSeries($data));

        return redirect()
            ->route('dashboard.accounts-payable.edit', $accounts[0])
            ->with('success', count($accounts) > 1
                ? 'Série de contas a pagar criada com sucesso.'
                : 'Conta a pagar criada com sucesso.');
    }

    public function edit(AccountPayable $accountPayable): View
    {
        $accountPayable->load('attachments');

        return view('dashboard.accounts-payable.edit', [
            'account' => $accountPayable,
            'currencies' => $this->acceptedCurrencies(),
        ]);
    }

    public function update(Request $request, AccountPayable $accountPayable): RedirectResponse
    {
        $data = $this->validatedData($request, $accountPayable);

        $accountPayable->update($data);

        return redirect()
            ->route('dashboard.accounts-payable.edit', $accountPayable)
            ->with('success', 'Conta a pagar atualizada com sucesso.');
    }

    public function destroy(AccountPayable $accountPayable): RedirectResponse
    {
        $accountPayable->delete();

        return redirect()
            ->route('dashboard.accounts-payable')
            ->with('success', 'Conta a pagar removida com sucesso.');
    }

    public function markPaid(Request $request, AccountPayable $accountPayable): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['nullable', 'date'],
        ]);

        $accountPayable->update([
            'status' => AccountPayable::STATUS_PAID,
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
        ]);

        return back()->with('success', 'Conta marcada como paga.');
    }

    public function cancel(AccountPayable $accountPayable): RedirectResponse
    {
        $accountPayable->update([
            'status' => AccountPayable::STATUS_CANCELLED,
            'paid_at' => null,
        ]);

        return back()->with('success', 'Conta cancelada.');
    }

    public function storeAttachment(Request $request, AccountPayable $accountPayable): RedirectResponse
    {
        $data = $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        foreach ($data['attachments'] as $file) {
            $path = $file->store("accounts-payable/{$accountPayable->id}", 'local');

            $accountPayable->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Anexos adicionados com sucesso.');
    }

    public function downloadAttachment(
        AccountPayable $accountPayable,
        AccountPayableAttachment $attachment
    ): StreamedResponse {
        abort_unless($attachment->account_payable_id === $accountPayable->id, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(
        AccountPayable $accountPayable,
        AccountPayableAttachment $attachment
    ): RedirectResponse {
        abort_unless($attachment->account_payable_id === $accountPayable->id, 404);

        $attachment->delete();

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    private function validatedData(Request $request, ?AccountPayable $accountPayable = null): array
    {
        $currencies = $this->acceptedCurrencies();

        $validator = validator($request->all(), [
            'supplier' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', Rule::in($currencies)],
            'status' => ['required', Rule::in(AccountPayable::statuses())],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_recurring' => ['boolean'],
            'recurrence_interval' => ['nullable', Rule::in(AccountPayable::recurrenceIntervals())],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:due_date'],
            'recurrence_occurrences' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $validator->after(function (Validator $validator) use ($request, $accountPayable): void {
            if (! $request->boolean('is_recurring')) {
                return;
            }

            if ($accountPayable !== null) {
                return;
            }

            if (! $request->filled('recurrence_interval')) {
                $validator->errors()->add('recurrence_interval', 'Escolha o intervalo da recorrência.');
            }

            if (! $request->filled('recurrence_ends_at') && ! $request->filled('recurrence_occurrences')) {
                $validator->errors()->add('recurrence_ends_at', 'Informe uma data final ou número de ocorrências.');
            }
        });

        $data = $validator->validate();
        $data['is_recurring'] = $request->boolean('is_recurring');

        if (! $data['is_recurring']) {
            $data['recurrence_interval'] = null;
            $data['recurrence_ends_at'] = null;
            $data['recurrence_occurrences'] = null;
            $data['recurrence_group_uuid'] = null;
            $data['occurrence_index'] = 1;
            $data['occurrence_total'] = 1;
        }

        if ($data['status'] !== AccountPayable::STATUS_PAID) {
            $data['paid_at'] = null;
        }

        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    private function acceptedCurrencies(): array
    {
        $currencies = json_decode($this->settingsService->getByKey('currency.accepted') ?? '', true);

        if (! is_array($currencies) || $currencies === []) {
            return ['EUR'];
        }

        return array_map('strtoupper', $currencies);
    }
}
