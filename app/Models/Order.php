<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\OrderCannotBeChangedByUserException;
use App\Exceptions\SubmittedOrderCannotBeChangedException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_cycle_id',
        'status',
        'total_price',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_price' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(OrderCycle::class, 'order_cycle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === OrderStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === OrderStatus::Submitted;
    }

    public function canBeChangedByUser(): bool
    {
        return $this->isDraft();
    }

    public function ensureCanBeChangedByUser(): void
    {
        if ($this->canBeChangedByUser()) {
            return;
        }

        if ($this->isSubmitted()) {
            throw SubmittedOrderCannotBeChangedException::forOrder($this);
        }

        throw OrderCannotBeChangedByUserException::forNonDraftOrder();
    }

    public function recalculateTotal(): void
    {
        $this->total_price = $this->items()->sum(DB::raw('price_snapshot * quantity'));
        $this->save();
    }
}
