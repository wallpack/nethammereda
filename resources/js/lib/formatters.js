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

export const formatCartPrice = (value) => {
    const amount = Math.round(Number(value) || 0);

    return `${amount.toString()} ₽`;
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
        discarded: 'Списано',
        expired: 'Списано',
    };

    return labels[status] ?? status;
};

export const orderStatusLabel = (status) => {
    const labels = {
        draft: 'Черновик',
        submitted: 'Отправлен',
        cancelled: 'Отменен',
    };

    return labels[status] ?? status;
};

export const formatDateTime = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export const expiryLabel = (value) => {
    const formatted = formatDateTime(value);

    return formatted ? `До ${formatted}` : 'Срок хранения не указан';
};
