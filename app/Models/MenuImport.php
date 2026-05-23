<?php

namespace App\Models;

use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'stored_path',
        'status',
        'format',
        'rows_total',
        'rows_valid',
        'rows_failed',
        'imported_by',
        'imported_at',
        'error_report',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'status' => MenuImportStatus::class,
            'format' => MenuImportFormat::class,
            'rows_total' => 'integer',
            'rows_valid' => 'integer',
            'rows_failed' => 'integer',
            'imported_at' => 'datetime',
            'error_report' => 'array',
            'options' => 'array',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     */
    public function errorRows(): array
    {
        $errors = data_get($this->error_report, 'errors', []);

        if (! is_array($errors)) {
            return [];
        }

        return collect($errors)
            ->filter(fn (mixed $error): bool => is_array($error))
            ->map(fn (array $error): array => [
                'row' => isset($error['row']) ? (int) $error['row'] : null,
                'field' => isset($error['field']) ? (string) $error['field'] : null,
                'message' => (string) ($error['message'] ?? ''),
                'value' => $error['value'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function errorSummary(): ?string
    {
        $summary = data_get($this->error_report, 'summary');

        return is_string($summary) && filled($summary) ? $summary : null;
    }
}
