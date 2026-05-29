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
use Illuminate\Http\Request;

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

    public function history(
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = request()->user();

        abort_if($user === null, 401);

        $canRepeat = $cycle !== null && $cycle->isOpenForOrdering();

        $orders = Order::query()
            ->with(['cycle', 'items.menuItem'])
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Submitted)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $orders
                ->map(fn (Order $order) => $this->orderHistoryPayload($order, $canRepeat))
                ->values(),
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

    public function repeat(
        Request $request,
        Order $order,
        CurrentOrderCycleResolver $resolver,
        OrderCycleAutoCloser $autoCloser,
        OrderService $orderService,
    ): JsonResponse {
        $cycle = $resolver->resolve();
        $autoCloser->closeIfExpired($cycle);
        $user = $request->user();

        abort_if($user === null, 401);
        abort_if((int) $order->user_id !== (int) $user->id, 404);

        if ($order->status !== OrderStatus::Submitted) {
            return response()->json([
                'message' => 'Можно повторять только отправленные заказы.',
            ], 422);
        }

        if ($cycle === null || ! $cycle->isOpenForOrdering()) {
            return response()->json([
                'message' => 'Повторить заказ можно, когда открыт приём заказов.',
            ], 422);
        }

        $mode = (string) $request->input('mode', 'replace');
        if ($mode !== 'replace') {
            return response()->json([
                'message' => 'Поддерживается только режим replace.',
            ], 422);
        }

        $result = $orderService->repeatSubmittedOrderForUser($order, $user, $cycle, $mode);

        if ($result['added_items_count'] === 0) {
            return response()->json([
                'message' => 'Не удалось повторить заказ: блюда из него сейчас недоступны.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'order' => $this->orderPayload($result['order']),
                'skipped_items' => $result['skipped_items'],
                'message' => 'Заказ добавлен в корзину.',
                'warning' => $result['warning'],
            ],
        ]);
    }
}
