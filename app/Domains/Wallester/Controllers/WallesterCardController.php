<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Wallester\Http\Requests\CreateWallesterCardRequest;
use App\Domains\Wallester\Http\Requests\RevealWallesterCardSensitiveRequest;
use App\Domains\Wallester\Services\WallesterCardSensitiveService;
use App\Domains\Wallester\Services\WallesterService;
use App\Http\Controllers\Controller;
use App\Models\CustomerWallesterCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallesterCardController extends Controller
{
    public function __construct(
        protected WallesterService $wallesterService,
        protected WallesterCardSensitiveService $cardSensitiveService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;

        $cards = CustomerWallesterCard::query()
            ->whereHas('wallesterAccount', fn ($query) => $query->where('customer_id', $customerId))
            ->operational()
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
    public function store(CreateWallesterCardRequest $request): JsonResponse
    {
        $card = $this->wallesterService->createCardForCustomer(
            $request->user(),
            $request->validated(),
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

    /** @return array<string, mixed> */
    private function transformCard(CustomerWallesterCard $card): array
    {
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
            'activated_at' => $card->activated_at?->toIso8601String(),
            'created_at' => $card->created_at?->toIso8601String(),
        ];
    }
}
