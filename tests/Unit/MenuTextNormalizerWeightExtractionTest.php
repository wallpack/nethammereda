<?php

namespace Tests\Unit;

use App\Support\MenuTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuTextNormalizerWeightExtractionTest extends TestCase
{
    #[Test]
    #[DataProvider('weightSuffixCases')]
    public function extracts_weight_from_supported_item_title_suffixes(string $title, ?string $expected): void
    {
        $normalizer = app(MenuTextNormalizer::class);

        $this->assertSame($expected, $normalizer->extractWeightFromItemTitle($title));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function weightSuffixCases(): array
    {
        return [
            'combo with compact grams in parentheses' => [
                'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)',
                '260 г',
            ],
            'spaced grams with trailing dot' => [
                'Сэндвич с ветчиной и сыром (150 г.)',
                '150 г',
            ],
            'soup with grams and dot' => [
                'Суп Солянка (300г.)',
                '300 г',
            ],
            'dot before gram unit' => [
                'Запеканка домашняя (230.г.)',
                '230 г',
            ],
            'trailing grams without parentheses' => [
                'Салат овощной 190гр',
                '190 г',
            ],
            'trailing grams with space' => [
                'Салат овощной 170 г.',
                '170 г',
            ],
            'title without weight suffix' => [
                'Котлета по-киевски с пюре',
                null,
            ],
        ];
    }
}
