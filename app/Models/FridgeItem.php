<?php

namespace App\Models;

use App\Enums\FridgeItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FridgeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_item_id',
        'menu_item_id',
        'title_snapshot',
        'quantity_total',
        'quantity_remaining',
        'status',
        'arrived_at',
        'eaten_at',
        'discarded_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'quantity_remaining' => 'integer',
            'status' => FridgeItemStatus::class,
            'arrived_at' => 'datetime',
            'eaten_at' => 'datetime',
            'discarded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}

