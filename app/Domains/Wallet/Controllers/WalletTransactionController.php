<?php

namespace App\Domains\Wallet\Controllers;

use App\Domains\Wallet\Services\WalletTransactionService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\P2PTransferRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletTransactionController extends Controller
{
    public function __construct(
        protected WalletTransactionService $transactions,
    ) {}

    /**
     * List Transactions
     *
     * @tags Transactions
     *
     * @queryParam per_page integer optional Results per page (max 100). Defaults to 15.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        return TransactionResource::collection(
            $this->transactions->list($request->user(), $perPage),
        );
    }

    /**
     * Get Transaction
     *
     * @tags Transactions
     */
    public function show(Request $request, int $id): TransactionResource
    {
        return TransactionResource::make($this->transactions->show($request->user(), $id));
    }

    /**
     * P2P Transfer
     *
     * @tags Transactions
     */
    public function p2p(P2PTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->transactions->p2pTransfer(
                sender: $request->user(),
                recipientUsername: $data['recipient_username'],
                amount: $data['amount'],
                currency: $data['currency'],
                deviceToken: $data['device_token'],
                pin: $data['pin'],
                description: $data['description'] ?? null,
                idempotencyKey: (string) $request->header('Idempotency-Key'),
                favorite: (bool) ($data['favorite'] ?? false),
            );
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'debit' => new TransactionResource($result['debit']),
            'credit' => new TransactionResource($result['credit']),
        ], 201);
    }
}
