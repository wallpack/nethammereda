<?php

namespace App\Filament\Support;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use Carbon\CarbonInterface;

class AdminDashboard
{
    public static function currentCycle(): ?OrderCycle
    {
        $priority = collect([
            OrderCycleStatus::SentToSupplier,
            OrderCycleStatus::Open,
            OrderCycleStatus::Closed,
            OrderCycleStatus::Draft,
            OrderCycleStatus::Delivered,
        ])
            ->map(fn (OrderCycleStatus $status, int $index): string => "WHEN '{$status->value}' THEN {$index}")
            ->implode(' ');

        $statuses = [
            OrderCycleStatus::SentToSupplier,
            OrderCycleStatus::Open,
            OrderCycleStatus::Closed,
            OrderCycleStatus::Draft,
            OrderCycleStatus::Delivered,
        ];

        return OrderCycle::query()
            ->with(['sentToSupplierBy', 'deliveredBy'])
            ->whereIn('status', array_map(fn (OrderCycleStatus $status): string => $status->value, $statuses))
            ->orderByRaw("CASE status {$priority} ELSE 99 END")
            ->orderByDesc('starts_at')
            ->first()
            ?? OrderCycle::query()
                ->with(['sentToSupplierBy', 'deliveredBy'])
                ->latest('starts_at')
                ->first();
    }

    public static function cyclePeriod(?OrderCycle $cycle): string
    {
        if (! $cycle) {
            return 'Цикл не создан';
        }

        $start = $cycle->starts_at?->format('d.m.Y') ?? 'без даты начала';
        $end = $cycle->closes_at?->format('d.m.Y') ?? 'без дедлайна';

        return "{$start} - {$end}";
    }

    public static function formatDateTime(?CarbonInterface $date): string
    {
        return $date?->format('d.m.Y H:i') ?? 'Не указано';
    }

    public static function timeUntilDeadline(?OrderCycle $cycle): string
    {
        if (! $cycle?->closes_at) {
            return 'Дедлайн не задан';
        }

        if ($cycle->closes_at->isPast()) {
            return 'Дедлайн прошел';
        }

        $minutes = (int) now()->diffInMinutes($cycle->closes_at);
        $days = intdiv($minutes, 60 * 24);
        $hours = intdiv($minutes % (60 * 24), 60);
        $remainingMinutes = $minutes % 60;

        if ($days > 0) {
            return "Осталось {$days} дн. {$hours} ч.";
        }

        if ($hours > 0) {
            return "Осталось {$hours} ч. {$remainingMinutes} мин.";
        }

        return "Осталось {$remainingMinutes} мин.";
    }

    public static function nextStep(?OrderCycle $cycle): string
    {
        if (! $cycle) {
            return 'Создать недельный цикл';
        }

        return match ($cycle->status) {
            OrderCycleStatus::Draft => 'Открыть заказ',
            OrderCycleStatus::Open => 'Закрыть заказ',
            OrderCycleStatus::Closed => 'Отправить поставщику',
            OrderCycleStatus::SentToSupplier => 'Отметить доставку',
            OrderCycleStatus::Delivered => 'Архивировать',
            OrderCycleStatus::Archived => 'Цикл завершен',
        };
    }

    public static function supplierStatus(?OrderCycle $cycle): string
    {
        if (! $cycle) {
            return 'Нет активного цикла';
        }

        return match ($cycle->status) {
            OrderCycleStatus::Draft, OrderCycleStatus::Open => 'Заказы еще собираются',
            OrderCycleStatus::Closed => 'Готово к отправке',
            OrderCycleStatus::SentToSupplier => 'Доставка ожидает отметки',
            OrderCycleStatus::Delivered => 'Доставка отмечена',
            OrderCycleStatus::Archived => 'Цикл в архиве',
        };
    }

    public static function money(int | float | string | null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, ',', ' ').' ₽';
    }
}
