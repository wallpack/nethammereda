<?php

namespace App\Models;

use App\Enums\OrderCycleStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'closes_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'closes_at' => 'datetime',
            'status' => OrderCycleStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'order_cycle_id');
    }

    public function isOpenForOrdering(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === OrderCycleStatus::Open
            && $now->lt($this->closes_at);
    }
}
