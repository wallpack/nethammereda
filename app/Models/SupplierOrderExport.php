<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_cycle_id',
        'exported_by',
        'exported_at',
        'rows_count',
        'total_quantity',
        'total_price',
        'format',
        'file_path',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
            'rows_count' => 'integer',
            'total_quantity' => 'integer',
            'total_price' => 'decimal:2',
            'snapshot_json' => 'array',
        ];
    }

    public function orderCycle(): BelongsTo
    {
        return $this->belongsTo(OrderCycle::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
