export const formatPrice = (value) => {
    const amount = Number(value ?? 0);

    if (Number.isNaN(amount)) {
        return '0 ₽';
    }

    const hasFraction = Math.abs(amount % 1) > 0.001;

    return `${amount.toLocaleString('ru-RU', {
        minimumFractionDigits: hasFraction ? 2 : 0,
        maximumFractionDigits: 2,
    })} ₽`;
};

export const compactNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return String(value ?? '-');
    }

    return amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 1,
    });
};

export const nutritionLine = (item) => {
    const kcal = item.calories ? `${compactNumber(item.calories)} ккал` : '-';
    const proteins = compactNumber(item.proteins);
    const fats = compactNumber(item.fats);
    const carbs = compactNumber(item.carbs);

    return `${kcal} • Б ${proteins} • Ж ${fats} • У ${carbs}`;
};

export const extractShelfLife = (item) => {
    const description = item.description ?? '';
    const match = description.match(/срок годности:\s*([^.,;]+)/i);

    return match ? match[1].trim() : null;
};

export const shelfLifeLabel = (item) => {
    const shelfLife = extractShelfLife(item);
    const daysMatch = shelfLife?.match(/(\d+)\s*сут/i);

    if (daysMatch?.[1]) {
        return `${Number(daysMatch[1]) * 24} ч`;
    }

    return shelfLife;
};

export const fridgeStatusLabel = (status) => {
    const labels = {
        in_fridge: 'В холодильнике',
        eaten: 'Съедено',
        discarded: 'Выброшено',
        expired: 'Просрочено',
    };

    return labels[status] ?? status;
};

export const orderStatusLabel = (status) => {
    const labels = {
        draft: 'Черновик',
        submitted: 'Подтвержден',
        cancelled: 'Отменен',
    };

    return labels[status] ?? status;
};
