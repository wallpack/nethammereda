<?php

namespace App\Services;

use App\Models\Order;

class OrderSummaryFormatter
{
    public function format(Order $order): string
    {
        if ($order->items->isEmpty()) {
            return 'Заказ пока пустой.';
        }

        $lines = $order->items
            ->map(function ($item) {
                return "- {$item->title_snapshot} ×{$item->quantity} ({$this->statusLabel($item->status->value)})";
            })
            ->all();

        $lines[] = '';
        $lines[] = 'Итого: '.number_format((float) $order->total_price, 2, '.', ' ').' ₽';

        return implode("\n", $lines);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'ordered' => 'заказано',
            'arrived' => 'приехало',
            'received' => 'получено',
            'eaten' => 'съедено',
            'cancelled' => 'отменено',
            default => $status,
        };
    }
}
