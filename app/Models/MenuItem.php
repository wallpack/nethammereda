<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        'image_path',
        'image_source',
        'image_assigned_at',
        'external_id',
        'supplier_code',
        'is_active',
    ];

    protected $appends = [
        'image_display_url',
    ];

    protected $hidden = [
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'proteins' => 'decimal:2',
            'fats' => 'decimal:2',
            'carbs' => 'decimal:2',
            'price' => 'decimal:2',
            'image_assigned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getImageDisplayUrlAttribute(): ?string
    {
        if ($this->isSafeLocalImagePath($this->image_path)) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->safeExternalImageUrl($this->image_url);
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

    private function isSafeLocalImagePath(?string $path): bool
    {
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        $path = str_replace('\\', '/', trim($path));

        if (str_contains($path, "\0") || str_starts_with($path, '/') || str_contains($path, '//')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        $extension = mb_strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function safeExternalImageUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) === false ? null : $url;
    }
}
