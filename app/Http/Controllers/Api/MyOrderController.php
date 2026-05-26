<?php

namespace App\Http\Controllers\Api;

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
                ],
            ]);
        }

        $order = Order::query()
            ->with(['items.menuItem'])
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id)
            ->first();

        return response()->json([
            'data' => [
                'cycle' => $this->cyclePayload($cycle),
                'order' => $order === null ? null : $this->orderPayload($order),
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
                'message' => 'Прием заказов для этой недели закрыт.',
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

        $order = Order::query()
            ->with(['cycle', 'items.menuItem'])
            ->where('user_id', $user->id)
            ->when($cycle !== null, fn ($query) => $query->where('order_cycle_id', $cycle->id))
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
