<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'composition',
        'weight',
        'calories',
        'proteins',
        'fats',
        'carbs',
        'price',
        'image_url',
        'external_id',
        'supplier_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'proteins' => 'decimal:2',
            'fats' => 'decimal:2',
            'carbs' => 'decimal:2',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'menu_item_id');
    }

    public function fridgeItems(): HasMany
    {
        return $this->hasMany(FridgeItem::class, 'menu_item_id');
    }
}
