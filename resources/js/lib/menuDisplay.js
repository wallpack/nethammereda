const TITLE_REPLACEMENTS = [
    [
        /Запеканка картофельнаяс куриным жульеном/giu,
        'Запеканка картофельная с куриным жульеном',
    ],
];

const UNIT_LABELS = new Map([
    ['г', 'г'],
    ['гр', 'г'],
    ['кг', 'кг'],
    ['мл', 'мл'],
    ['л', 'л'],
    ['шт', 'шт'],
]);

const normalizeWhitespace = (value) => String(value ?? '').trim().replace(/\s+/g, ' ');

export const menuItemDisplayTitle = (item) => {
    let title = normalizeWhitespace(item?.title);

    TITLE_REPLACEMENTS.forEach(([pattern, replacement]) => {
        title = title.replace(pattern, replacement);
    });

    return title;
};

export const normalizeMenuMeta = (value) => {
    const meta = normalizeWhitespace(value);

    if (meta === '') {
        return null;
    }

    const compactUnitMatch = meta.match(/^(\d+(?:[,.]\d+)?)\s*(г|гр|кг|мл|л|шт)\.?$/iu);

    if (!compactUnitMatch) {
        return meta;
    }

    const [, amount, rawUnit] = compactUnitMatch;
    const unit = UNIT_LABELS.get(rawUnit.toLowerCase().replace('.', '')) ?? rawUnit.toLowerCase().replace('.', '');

    return `${amount.replace('.', ',')} ${unit}`;
};

export const menuItemDisplayMeta = (item) => {
    return normalizeMenuMeta(item?.display_weight)
        || normalizeMenuMeta(item?.weight)
        || normalizeMenuMeta(item?.volume)
        || normalizeMenuMeta(item?.quantity_unit)
        || normalizeMenuMeta(item?.unit);
};
