<?php

namespace Tests\Unit;

use App\Support\MenuCatalogTitleFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuCatalogTitleFormatterTest extends TestCase
{
    #[Test]
    #[DataProvider('catalogTitleCases')]
    public function it_normalizes_supplier_name_to_laconic_catalog_title(string $supplierName, string $expectedTitle): void
    {
        $formatter = app(MenuCatalogTitleFormatter::class);

        $this->assertSame($expectedTitle, $formatter->catalogTitle($supplierName));
    }

    #[Test]
    public function supplier_name_method_keeps_full_supplier_title_except_trim(): void
    {
        $formatter = app(MenuCatalogTitleFormatter::class);

        $this->assertSame(
            'Вафли "Гранд" с начинкой (сгущ.) (20шт)',
            $formatter->supplierName('  Вафли "Гранд" с начинкой (сгущ.) (20шт)  '),
        );
    }

    #[Test]
    public function combo_kiev_cutlet_title_keeps_distinguishing_marker(): void
    {
        $formatter = app(MenuCatalogTitleFormatter::class);
        $combo = $formatter->catalogTitle('Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)');
        $regular = $formatter->catalogTitle('Котлета (по-Киевски) с картофельным пюре (260г)');

        $this->assertNotSame($regular, $combo);
        $this->assertStringContainsString('Комбо', $combo);
        $this->assertStringContainsStringIgnoringCase('фасол', $combo);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function catalogTitleCases(): array
    {
        return [
            'waffles with piece count and short condensed milk form' => [
                'Вафли Гранд с начинкой (сгущ.) (20шт)',
                'Вафли Гранд со сгущёнкой',
            ],
            'kiev style lower-cased after po hyphen' => [
                'Котлета по-Киевски с пюре',
                'Котлета по-киевски с пюре',
            ],
            'combo with weight keeps combo and beans marker' => [
                'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)',
                'Комбо: котлета по-киевски с пюре и фасолью',
            ],
            'rice milk porridge polished' => [
                'Каша рисовая (молочная)',
                'Рисовая каша на молоке',
            ],
            'hercules milk porridge polished' => [
                'Каша геркулесовая (молочная)',
                'Геркулесовая каша на молоке',
            ],
            'corn milk porridge polished' => [
                'Каша кукурузная (молочная)',
                'Кукурузная каша на молоке',
            ],
            'chef cutlet with puree polished' => [
                'Котлетка от шефа (куриная) с картофельным пюре',
                'Куриная котлетка от шефа с пюре',
            ],
            'pork chop with green noodles polished' => [
                'Отбивная (из свинины с зеленой лапшой)',
                'Свиная отбивная с зелёной лапшой',
            ],
            'baked cabbage pie polished' => [
                'Пирожок с капустой (печеный)',
                'Печёный пирожок с капустой',
            ],
            'baked potato pie polished' => [
                'Пирожок с картошкой (печеный)',
                'Печёный пирожок с картошкой',
            ],
            'shawarma with sauce and filling polished' => [
                'Шаурма с сырным соусом (курица и картофель)',
                'Шаурма с курицей, картофелем и сырным соусом',
            ],
            'big pizza title normalized casing' => [
                'Большая Пицца "С колбасой и сыром" (630 г.)',
                'Большая пицца с колбасой и сыром',
            ],
            'home style dumplings lower case' => [
                'Пельмени "По-домашнему" (200г)',
                'Пельмени по-домашнему',
            ],
            'hot sandwich conjunction fix' => [
                'Горячий бутерброд с ветчиной, сыром (90г)',
                'Горячий бутерброд с ветчиной и сыром',
            ],
            'crispy chicken chop abbreviation fix' => [
                'Отбивная из куриной грудки в хруст. корочке с запеч.картоф.(260 г.)',
                'Куриная отбивная с запечённым картофелем',
            ],
            'baked onion egg pie polished' => [
                'Пирожок с луком и яйцом печеный (150г)',
                'Печёный пирожок с луком и яйцом',
            ],
            'pike style snack lower case and spacing' => [
                'Закуска По-щучьему велению (крабовые палочки, яйцо, сыр, зелень, майонез) (140 г.)',
                'Закуска по щучьему велению',
            ],
            'soup borscht without quotes and grams' => [
                'Суп "Борщ" (300 г.)',
                'Борщ',
            ],
            'soup gorokhovy with novinka becomes natural title' => [
                'Суп "Гороховый" (300г.) новинка',
                'Гороховый суп',
            ],
            'blinchiki naslazhdenie composition retained without marketing word' => [
                'Блинчики Наслаждение (с творожным сыром и маком) (160г.)',
                'Блинчики с творожным сыром и маком',
            ],
            'blinchiki fantaziya normalized' => [
                'Блинчики Фантазия (с маком и сгущенным молоком) (180 г)',
                'Блинчики с маком и сгущенным молоком',
            ],
            'quoted student name with missing space fixed' => [
                '"Студенческий"с капустой и ветчиной',
                'Студенческий с капустой и ветчиной',
            ],
            'hot dog with gms and quotes normalized' => [
                'Хот дог "Мексика" (ГМС) (170 г.)',
                'Хот-дог Мексика',
            ],
            'triangle marker removed from sandwich' => [
                'Сэндвич с копченой курицей (150 г.) (треугольный)',
                'Сэндвич с копченой курицей',
            ],
        ];
    }
}
