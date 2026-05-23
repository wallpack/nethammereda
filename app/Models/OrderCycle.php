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
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'closes_at' => 'datetime',
            'status' => OrderCycleStatus::class,
            'sent_to_supplier_at' => 'datetime',
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

    public function isOpenForOrdering(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === OrderCycleStatus::Open
            && $now->lt($this->closes_at);
    }
}
