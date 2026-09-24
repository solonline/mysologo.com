<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Auth\Services\DeviceAuthService;
use App\Domains\Wallester\Http\Requests\ActivateCardByCVVRequest;
use App\Domains\Wallester\Http\Requests\BlockWallesterCardRequest;
use App\Domains\Wallester\Http\Requests\ChangeWallesterCardPinRequest;
use App\Domains\Wallester\Http\Requests\CreateWallesterCardRequest;
use App\Domains\Wallester\Http\Requests\RevealWallesterCardSensitiveRequest;
use App\Domains\Wallester\Services\WallesterCardSensitiveService;
use App\Domains\Wallester\Services\WallesterService;
use App\Http\Controllers\Controller;
use App\Models\CustomerWallesterCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WallesterCardController extends Controller
{
    public function __construct(
        protected WallesterService $wallesterService,
        protected WallesterCardSensitiveService $cardSensitiveService,
        protected DeviceAuthService $deviceAuthService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;

        $cards = CustomerWallesterCard::query()
            ->with('customerDeliveryAddress')
            ->whereHas('wallesterAccount', fn ($query) => $query->where('customer_id', $customerId))
            ->managed()
            ->orderByDesc('id')
            ->get()
            ->map(fn (CustomerWallesterCard $card) => $this->transformCard($card));

        return response()->json([
            'data' => $cards,
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function issuanceQuote(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->wallesterService->issuanceQuoteForCustomer($request->user()),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function store(CreateWallesterCardRequest $request): JsonResponse
    {
        $this->verifyCustomerPin($request);

        $payload = $request->validated();
        unset($payload['pin'], $payload['device_token']);

        $card = $this->wallesterService->createCardForCustomer(
            $request->user(),
            $payload,
        );

        return response()->json([
            'data' => $this->transformCard($card),
        ], 201);
    }

    /**
     * @hideFromApiDocs
     */
    public function show(Request $request, CustomerWallesterCard $card): JsonResponse
    {
        return response()->json([
            'data' => $this->wallesterService->showCardForCustomer($request->user(), $card),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function sensitive(RevealWallesterCardSensitiveRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        return response()->json([
            'data' => $this->cardSensitiveService->revealForCustomer(
                $request->user(),
                $card,
                $request->validated(),
            ),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function encryptedPin(RevealWallesterCardSensitiveRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->cardSensitiveService->verifyStepUpCredentials(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'pin' => $this->wallesterService->getEncryptedPinForCustomer($request->user(), $card),
            ],
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function activate(Request $request, CustomerWallesterCard $card): JsonResponse
    {
        $activatedCard = $this->wallesterService->activateVirtualCardForCustomer(
            $request->user(),
            $card,
        );

        return response()->json([
            'data' => $this->transformCard($activatedCard),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function activateByCVV(ActivateCardByCVVRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->verifyCustomerPin($request);

        $activatedCard = $this->wallesterService->activateCardByCVVForCustomer(
            $request->user(),
            $card,
            (string) $request->validated('cvv'),
        );

        return response()->json([
            'data' => $this->transformCard($activatedCard),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function block(BlockWallesterCardRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->verifyCustomerPin($request);

        $blockedCard = $this->wallesterService->blockCardForCustomer(
            $request->user(),
            $card,
        );

        return response()->json([
            'data' => $this->transformCard($blockedCard),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function unblock(BlockWallesterCardRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->verifyCustomerPin($request);

        $unblockedCard = $this->wallesterService->unblockCardForCustomer(
            $request->user(),
            $card,
        );

        return response()->json([
            'data' => $this->transformCard($unblockedCard),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function close(BlockWallesterCardRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->verifyCustomerPin($request);

        $closedCard = $this->wallesterService->closeCardForCustomer(
            $request->user(),
            $card,
        );

        return response()->json([
            'data' => $this->transformCard($closedCard),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function changePin(ChangeWallesterCardPinRequest $request, CustomerWallesterCard $card): JsonResponse
    {
        $this->verifyCustomerPin($request);
        $data = $this->wallesterService->changePinForCustomer(
            $request->user(),
            $card,
            (string) $request->validated('pin_card'),
        );

        Log::channel('wallester')->info('wallester_card_change_pin', [
            'card_id' => $card->id,
            'data' => $data->toArray(),
        ]);

        return response()->json([
            'data' => $data,
        ]);
    }

    private function verifyCustomerPin(CreateWallesterCardRequest|BlockWallesterCardRequest|ActivateCardByCVVRequest|ChangeWallesterCardPinRequest $request): void
    {
        $this->deviceAuthService->verifyPin(
            $request->user(),
            (string) $request->validated('device_token'),
            (string) $request->validated('pin'),
        );
    }

    /** @return array<string, mixed> */
    private function transformCard(CustomerWallesterCard $card): array
    {
        $card->loadMissing('customerDeliveryAddress');
        $address = $card->customerDeliveryAddress;

        return [
            'id' => $card->id,
            'wallester_card_id' => $card->wallester_card_id,
            'type' => $card->type,
            'status' => $card->status,
            'name' => $card->name,
            'masked_card_number' => $card->masked_card_number,
            'expiry_date' => $card->expiry_date?->toIso8601String(),
            'security' => $card->security,
            'limits' => $card->limits,
            'three_d_secure_settings' => $card->three_d_secure_settings,
            'delivery_address' => $address === null ? null : [
                'address' => $address->address,
                'city' => $address->city,
                'state' => $address->state,
                'country' => $address->country,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'phone' => $address->phone,
                'postal_code' => $address->postal_code,
            ],
            'block_type' => $card->block_type,
            'blocked_at' => $card->blocked_at?->toIso8601String(),
            'close_reason' => $card->close_reason,
            'closed_at' => $card->closed_at?->toIso8601String(),
            'activated_at' => $card->activated_at?->toIso8601String(),
            'created_at' => $card->created_at?->toIso8601String(),
        ];
    }
}
