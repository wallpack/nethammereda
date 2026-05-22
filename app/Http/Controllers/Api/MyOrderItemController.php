<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMyOrderItemRequest;
use App\Http\Requests\UpdateMyOrderItemRequest;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Services\CurrentOrderCycleResolver;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class MyOrderItemController extends Controller
{
    use FormatsApiPayloads;

    public function store(
        StoreMyOrderItemRequest $request,
        CurrentOrderCycleResolver $resolver,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $user = $request->user();

        abort_if($user === null, 401);

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Прием заказов для этой недели закрыт.',
            ], 422);
        }

        $menuItem = MenuItem::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('menu_item_id'));

        $order = $orderService->getOrCreateOrder($user, $cycle);
        $this->authorize('update', $order);

        if ($order->status !== OrderStatus::Draft) {
            return $this->submittedOrderCannotBeChangedResponse();
        }

        $quantity = $request->integer('quantity', 1);

        $item = OrderItem::query()->firstOrNew([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
        ]);

        $item->fill([
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'status' => OrderItemStatus::Ordered,
        ]);

        $item->quantity = $item->exists ? $item->quantity + $quantity : $quantity;
        $item->save();

        $orderService->markAsDraft($order);
        $orderService->recalculate($order);

        return response()->json([
            'data' => $this->orderPayload($order->fresh(['cycle', 'items.menuItem'])),
        ]);
    }

    public function update(
        UpdateMyOrderItemRequest $request,
        OrderItem $orderItem,
        OrderService $orderService,
    ): JsonResponse {
        $orderItem->loadMissing('order.cycle');
        $this->authorize('update', $orderItem);

        if (! $orderItem->order?->cycle?->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Заказ уже нельзя редактировать.',
            ], 422);
        }

        if ($orderItem->order->status !== OrderStatus::Draft) {
            return $this->submittedOrderCannotBeChangedResponse();
        }

        $orderItem->quantity = $request->integer('quantity');
        $orderItem->save();

        $order = $orderItem->order;
        $orderService->markAsDraft($order);
        $orderService->recalculate($order);

        return response()->json([
            'data' => $this->orderPayload($order->fresh(['cycle', 'items.menuItem'])),
        ]);
    }

    public function destroy(OrderItem $orderItem, OrderService $orderService): JsonResponse
    {
        $orderItem->loadMissing('order.cycle');
        $this->authorize('delete', $orderItem);

        if (! $orderItem->order?->cycle?->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Заказ уже нельзя редактировать.',
            ], 422);
        }

        if ($orderItem->order->status !== OrderStatus::Draft) {
            return $this->submittedOrderCannotBeChangedResponse();
        }

        $order = $orderItem->order;
        $orderItem->delete();

        if ($order !== null) {
            $orderService->markAsDraft($order);
            $orderService->recalculate($order);
        }

        return response()->json([
            'data' => $order === null ? null : $this->orderPayload($order->fresh(['cycle', 'items.menuItem'])),
        ]);
    }

    public function markReceived(OrderItem $orderItem): JsonResponse
    {
        $orderItem->loadMissing('order');
        $this->authorize('markReceived', $orderItem);

        if (! in_array($orderItem->status, [OrderItemStatus::Arrived, OrderItemStatus::Received, OrderItemStatus::Eaten], true)) {
            return response()->json([
                'message' => 'Позиция еще не отмечена как доставленная.',
            ], 422);
        }

        if ($orderItem->status === OrderItemStatus::Arrived) {
            $orderItem->status = OrderItemStatus::Received;
            $orderItem->save();
        }

        return response()->json([
            'data' => $orderItem->fresh(),
        ]);
    }

    public function markEaten(OrderItem $orderItem): JsonResponse
    {
        $orderItem->loadMissing('order');
        $this->authorize('markEaten', $orderItem);

        if (! in_array($orderItem->status, [OrderItemStatus::Arrived, OrderItemStatus::Received, OrderItemStatus::Eaten], true)) {
            return response()->json([
                'message' => 'Отметить как съеденное можно только доставленную позицию.',
            ], 422);
        }

        $orderItem->status = OrderItemStatus::Eaten;
        $orderItem->save();

        return response()->json([
            'data' => $orderItem->fresh(),
        ]);
    }

    private function submittedOrderCannotBeChangedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Submitted orders cannot be changed.',
        ], 422);
    }
}
