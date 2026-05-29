<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Api\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMyOrderItemRequest;
use App\Http\Requests\UpdateMyOrderItemRequest;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Services\CurrentOrderCycleResolver;
use App\Services\OrderCycleAutoCloser;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyOrderItemController extends Controller
{
    use FormatsApiPayloads;

    public function store(
        StoreMyOrderItemRequest $request,
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = $request->user();

        abort_if($user === null, 401);

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return $this->closedOrderingResponse();
        }

        $menuItem = MenuItem::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('menu_item_id'));

        $order = $orderService->getOrCreateOrder($user, $cycle);
        $this->authorize('update', $order);

        $quantity = $request->integer('quantity', 1);
        $order = $orderService->addItemForUser($order, $menuItem, $quantity);

        return response()->json([
            'data' => $this->orderPayload($order),
        ]);
    }

    public function update(
        UpdateMyOrderItemRequest $request,
        OrderItem $orderItem,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse {
        $orderItem->loadMissing('order.cycle');
        $autoCloser->closeIfExpired($orderItem->order?->cycle);

        $user = $request->user();
        abort_if($user === null, 401);

        $order = $orderItem->order;
        abort_if($order === null, 404);
        abort_if(! $user->isAdmin() && (int) $order->user_id !== (int) $user->id, 403);

        if (! $order->cycle?->isOpenForOrdering()) {
            return $this->closedOrderingResponse();
        }

        $this->authorize('update', $orderItem);

        $updatedOrder = $orderService->updateItemQuantityForUser($orderItem, $request->integer('quantity'));

        return response()->json([
            'data' => $this->orderPayload($updatedOrder),
        ]);
    }

    public function destroy(
        Request $request,
        OrderItem $orderItem,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse
    {
        $orderItem->loadMissing('order.cycle');
        $autoCloser->closeIfExpired($orderItem->order?->cycle);

        $user = $request->user();
        abort_if($user === null, 401);

        $order = $orderItem->order;
        abort_if($order === null, 404);
        abort_if(! $user->isAdmin() && (int) $order->user_id !== (int) $user->id, 403);

        if (! $order->cycle?->isOpenForOrdering()) {
            return $this->closedOrderingResponse();
        }

        $this->authorize('delete', $orderItem);

        $updatedOrder = $orderService->deleteItemForUser($orderItem);

        return response()->json([
            'data' => $updatedOrder === null ? null : $this->orderPayload($updatedOrder),
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

    private function closedOrderingResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Приём заказов закрыт.',
        ], 422);
    }
}
