<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\P2PTransferRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage      = min((int) $request->input('per_page', 15), 100);
        $transactions = $this->transactionService->list($request->user(), $perPage);

        return TransactionResource::collection($transactions);
    }

    public function show(Request $request, int $id): TransactionResource
    {
        $transaction = $this->transactionService->show($request->user(), $id);

        return TransactionResource::make($transaction);
    }

    public function p2p(P2PTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->transactionService->p2pTransfer(
                sender:            $request->user(),
                recipientUsername: $data['recipient_username'],
                amount:            $data['amount'],
                currency:          $data['currency'],
                deviceToken:       $data['device_token'],
                pin:               $data['pin'],
                description:       $data['description'] ?? null,
            );
        } catch (ApiException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'debit'  => new TransactionResource($result['debit']),
            'credit' => new TransactionResource($result['credit']),
        ], 201);
    }
}
