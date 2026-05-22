<?php

namespace App\Http\Controllers\Api;

use App\Enums\FridgeItemStatus;
use App\Http\Controllers\Controller;
use App\Models\FridgeItem;
use App\Services\FridgeItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyFridgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $this->authorize('viewAny', FridgeItem::class);

        $items = FridgeItem::query()
            ->where('user_id', $user->id)
            ->where('status', FridgeItemStatus::InFridge)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('arrived_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'active_count' => $items->count(),
                'total_portions' => $items->sum('quantity_remaining'),
                'expiring_soon_count' => $items
                    ->filter(fn (FridgeItem $item): bool => $item->expires_at !== null
                        && $item->expires_at->betweenIncluded(now(), now()->addDay()))
                    ->count(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $this->authorize('viewAny', FridgeItem::class);

        $items = FridgeItem::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                FridgeItemStatus::Eaten,
                FridgeItemStatus::Discarded,
                FridgeItemStatus::Expired,
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function eatOne(FridgeItem $fridgeItem, FridgeItemService $service): JsonResponse
    {
        $this->authorize('update', $fridgeItem);

        $updated = $service->eatOne($fridgeItem);

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function eatAll(FridgeItem $fridgeItem, FridgeItemService $service): JsonResponse
    {
        $this->authorize('update', $fridgeItem);

        $updated = $service->eatAll($fridgeItem);

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function discard(FridgeItem $fridgeItem, FridgeItemService $service): JsonResponse
    {
        $this->authorize('update', $fridgeItem);

        $updated = $service->discard($fridgeItem);

        return response()->json([
            'data' => $updated,
        ]);
    }
}
