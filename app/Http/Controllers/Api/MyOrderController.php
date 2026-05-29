<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CurrentOrderCycleResolver;
use App\Services\OrderCycleAutoCloser;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class MyOrderController extends Controller
{
    use FormatsApiPayloads;

    public function show(
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
    ): JsonResponse
    {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = request()->user();

        if ($cycle === null || $user === null) {
            return response()->json([
                'data' => [
                    'cycle' => null,
                    'order' => null,
                    'draft_unavailable' => false,
                    'draft_unavailable_message' => null,
                ],
            ]);
        }

        $orderQuery = Order::query()
            ->with(['items.menuItem'])
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id);

        $draftUnavailable = false;

        if (! $cycle->isOpenForOrdering()) {
            $draftUnavailable = (clone $orderQuery)
                ->where('status', OrderStatus::Draft)
                ->exists();

            $orderQuery->where('status', OrderStatus::Submitted);
        }

        $order = $orderQuery->first();

        return response()->json([
            'data' => [
                'cycle' => $this->cyclePayload($cycle),
                'order' => $order === null ? null : $this->orderPayload($order),
                'draft_unavailable' => $draftUnavailable,
                'draft_unavailable_message' => $draftUnavailable
                    ? 'Цикл закрыт, черновик заказа больше недоступен.'
                    : null,
            ],
        ]);
    }

    public function submit(
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = request()->user();

        abort_if($user === null, 401);

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Приём заказов закрыт.',
            ], 422);
        }

        $order = $orderService->getOrCreateOrder($user, $cycle);
        $this->authorize('update', $order);

        $order = $orderService->submit($order);

        return response()->json([
            'data' => $this->orderPayload($order),
        ]);
    }

    public function reopen(
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = request()->user();

        abort_if($user === null, 401);

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Приём заказов закрыт.',
            ], 422);
        }

        $order = Order::query()
            ->with(['cycle', 'items.menuItem'])
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id)
            ->latest('id')
            ->first();

        abort_if($order === null, 404);

        $autoCloser->closeIfExpired($order->cycle);
        $order = $orderService->reopenForUserEditing($order, $user);

        return response()->json([
            'data' => $this->orderPayload($order),
        ]);
    }
}
