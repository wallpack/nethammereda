<?php

namespace App\Models;

use App\Enums\OrderCycleStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'closes_at',
        'status',
        'sent_to_supplier_at',
        'sent_to_supplier_by',
        'delivered_at',
        'delivered_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'closes_at' => 'datetime',
            'status' => OrderCycleStatus::class,
            'sent_to_supplier_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'order_cycle_id');
    }

    public function supplierOrderExports(): HasMany
    {
        return $this->hasMany(SupplierOrderExport::class, 'order_cycle_id');
    }

    public function sentToSupplierBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_to_supplier_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function canTransitionTo(OrderCycleStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    public function transitionTo(OrderCycleStatus $status, array $attributes = []): self
    {
        if (! $this->canTransitionTo($status)) {
            throw new \DomainException('Invalid order cycle status transition.');
        }

        $this->forceFill([
            ...$attributes,
            'status' => $status,
        ])->save();

        return $this;
    }

    public function effectiveOrderState(?CarbonInterface $now = null): string
    {
        if ($this->status !== OrderCycleStatus::Open) {
            return match ($this->status) {
                OrderCycleStatus::Draft, null => 'draft',
                OrderCycleStatus::Delivered => 'delivered',
                OrderCycleStatus::Archived => 'archived',
                default => 'closed',
            };
        }

        $now = $this->businessNow($now);
        $startsAt = $this->starts_at?->copy()->setTimezone($now->getTimezone());
        $closesAt = $this->closes_at?->copy()->setTimezone($now->getTimezone());

        if ($startsAt === null || $closesAt === null) {
            return 'closed';
        }

        if ($now->lt($startsAt)) {
            return 'upcoming';
        }

        if ($now->gte($closesAt)) {
            return 'closed';
        }

        return 'open';
    }

    public function isOpenForOrdering(?CarbonInterface $now = null): bool
    {
        return $this->effectiveOrderState($now) === 'open';
    }

    public function isUpcomingForOrdering(?CarbonInterface $now = null): bool
    {
        return $this->effectiveOrderState($now) === 'upcoming';
    }

    private function businessNow(?CarbonInterface $now = null): CarbonInterface
    {
        $timezone = config('lunch.business_timezone', config('app.timezone', 'UTC'));

        return ($now ?? now($timezone))->copy()->setTimezone($timezone);
    }
}
