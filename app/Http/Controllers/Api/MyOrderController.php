<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CurrentOrderCycleResolver;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class MyOrderController extends Controller
{
    public function show(CurrentOrderCycleResolver $resolver): JsonResponse
    {
        $cycle = $resolver->resolve();
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
                'cycle' => [
                    'id' => $cycle->id,
                    'title' => $cycle->title,
                    'starts_at' => $cycle->starts_at,
                    'closes_at' => $cycle->closes_at,
                    'status' => $cycle->status->value,
                    'is_open_for_ordering' => $cycle->isOpenForOrdering(),
                ],
                'order' => $order,
            ],
        ]);
    }

    public function submit(
        CurrentOrderCycleResolver $resolver,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $user = request()->user();

        abort_if($user === null, 401);

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Прием заказов для этой недели закрыт.',
            ], 422);
        }

        $order = $orderService->getOrCreateOrder($user, $cycle);
        $this->authorize('update', $order);

        if ($order->items()->count() === 0) {
            return response()->json([
                'message' => 'Нельзя отправить пустой заказ.',
            ], 422);
        }

        $orderService->recalculate($order);
        $order->status = OrderStatus::Submitted;
        $order->submitted_at = now();
        $order->save();

        return response()->json([
            'data' => $order->fresh(['items.menuItem']),
        ]);
    }
}
