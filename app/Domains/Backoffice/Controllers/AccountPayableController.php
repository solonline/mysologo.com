<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CancelAccountPayableAction;
use App\Domains\Backoffice\Actions\CreateAccountPayableAction;
use App\Domains\Backoffice\Actions\DeleteAccountPayableAction;
use App\Domains\Backoffice\Actions\DeleteAccountPayableAttachmentAction;
use App\Domains\Backoffice\Actions\MarkAccountPayablePaidAction;
use App\Domains\Backoffice\Actions\StoreAccountPayableAttachmentsAction;
use App\Domains\Backoffice\Actions\UpdateAccountPayableAction;
use App\Domains\Backoffice\Queries\GetAcceptedCurrenciesQuery;
use App\Domains\Backoffice\Queries\ListAccountsPayableQuery;
use App\Domains\Backoffice\Requests\MarkAccountPayablePaidRequest;
use App\Domains\Backoffice\Requests\SaveAccountPayableRequest;
use App\Domains\Backoffice\Requests\StoreAccountPayableAttachmentsRequest;
use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\AccountPayableAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountPayableController extends Controller
{
    public function __construct(
        protected ListAccountsPayableQuery $listAccounts,
        protected GetAcceptedCurrenciesQuery $currencies,
        protected CreateAccountPayableAction $createAccount,
        protected UpdateAccountPayableAction $updateAccount,
        protected DeleteAccountPayableAction $deleteAccount,
        protected MarkAccountPayablePaidAction $markPaid,
        protected CancelAccountPayableAction $cancelAccount,
        protected StoreAccountPayableAttachmentsAction $storeAttachments,
        protected DeleteAccountPayableAttachmentAction $deleteAttachment,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'search', 'supplier', 'category', 'currency', 'status', 'due_from', 'due_to',
        ]);

        if ($request->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        $result = $this->listAccounts->execute($filters);

        return view('dashboard.accounts-payable.index', [
            'accounts' => $result->accounts->withQueryString(),
            'categories' => $result->categories,
            'currencies' => $result->currencies,
            'statuses' => $result->statuses,
        ]);
    }

    public function create()
    {
        return view('dashboard.accounts-payable.create', [
            'account' => null,
            'currencies' => $this->currencies->execute(),
        ]);
    }

    public function store(SaveAccountPayableRequest $request)
    {
        $accounts = $this->createAccount->execute($request->accountPayableAttributes());

        return redirect()
            ->route('dashboard.accounts-payable.edit', $accounts[0])
            ->with('success', count($accounts) > 1
                ? 'Série de contas a pagar criada com sucesso.'
                : 'Conta a pagar criada com sucesso.');
    }

    public function edit(AccountPayable $accountPayable)
    {
        return view('dashboard.accounts-payable.edit', [
            'account' => $this->listAccounts->findForEdit($accountPayable),
            'currencies' => $this->currencies->execute(),
        ]);
    }

    public function update(SaveAccountPayableRequest $request, AccountPayable $accountPayable)
    {
        $this->updateAccount->execute($accountPayable, $request->accountPayableAttributes());

        return redirect()
            ->route('dashboard.accounts-payable.edit', $accountPayable)
            ->with('success', 'Conta a pagar atualizada com sucesso.');
    }

    public function destroy(AccountPayable $accountPayable)
    {
        $this->deleteAccount->execute($accountPayable);

        return redirect()
            ->route('dashboard.accounts-payable')
            ->with('success', 'Conta a pagar removida com sucesso.');
    }

    public function markPaid(MarkAccountPayablePaidRequest $request, AccountPayable $accountPayable)
    {
        $this->markPaid->execute($accountPayable, $request->validated('paid_at'));

        return back()->with('success', 'Conta marcada como paga.');
    }

    public function cancel(AccountPayable $accountPayable)
    {
        $this->cancelAccount->execute($accountPayable);

        return back()->with('success', 'Conta cancelada.');
    }

    public function storeAttachment(StoreAccountPayableAttachmentsRequest $request, AccountPayable $accountPayable)
    {
        $this->storeAttachments->execute($accountPayable, $request->file('attachments', []));

        return back()->with('success', 'Anexos adicionados com sucesso.');
    }

    public function downloadAttachment(
        AccountPayable $accountPayable,
        AccountPayableAttachment $attachment,
    ): StreamedResponse {
        abort_unless($attachment->account_payable_id === $accountPayable->id, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(AccountPayable $accountPayable, AccountPayableAttachment $attachment)
    {
        $this->deleteAttachment->execute($accountPayable, $attachment);

        return back()->with('success', 'Anexo removido com sucesso.');
    }
}
