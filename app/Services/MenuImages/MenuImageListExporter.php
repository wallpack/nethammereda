<?php

namespace App\Services\MenuImages;

use App\Models\MenuItem;
use App\Services\MenuAudit\CsvReportWriter;
use App\Support\MenuTextNormalizer;
use Illuminate\Support\Facades\File;

class MenuImageListExporter
{
    public function __construct(
        private readonly MenuTextNormalizer $normalizer,
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array{path: string, count: int}
     */
    public function export(): array
    {
        $directory = storage_path('app/menu-images');
        $path = $directory.'/menu-items-for-images.csv';
        File::ensureDirectoryExists($directory);

        $rows = MenuItem::query()
            ->with('category:id,name')
            ->orderBy('title')
            ->get()
            ->map(function (MenuItem $item): array {
                $filename = $this->normalizer->suggestedFilename($item->id, $item->title);

                return [
                    'id' => $item->id,
                    'name' => $item->title,
                    'category' => $item->category?->name,
                    'description' => $item->description,
                    'suggested_filename' => $filename,
                    'prompt' => $this->prompt($item->title, $filename),
                ];
            })
            ->all();

        $this->csv->write($path, [
            'id',
            'name',
            'category',
            'description',
            'suggested_filename',
            'prompt',
        ], $rows);

        return [
            'path' => $path,
            'count' => count($rows),
        ];
    }

    private function prompt(string $dishName, string $filename): string
    {
        return 'Use the attached screenshot as a style reference for the food photos only. Create a clean food delivery catalog product photo of “'.$dishName.'”. Style requirements: isolated dish, centered composition, simple white or light ceramic plate/bowl, full dish visible, nothing cropped, clean off-white or light gray background, soft studio lighting, subtle shadow, top-down or slight 3/4 angle, realistic appetizing food, modern e-commerce menu card look. Do not include people, hands, cutlery, napkins, packaging, table props, restaurant interior, dark background, text, logos, labels, decorative objects. Composition: square image, dish takes 70–80% of the frame, clean empty space around the dish, consistent camera angle and lighting with the reference image. After generating, save as: '.$filename.'.';
    }
}
