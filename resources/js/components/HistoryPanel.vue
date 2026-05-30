<script setup>
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { fridgeStatusLabel } from '@/lib/formatters';
import { History } from 'lucide-vue-next';

const props = defineProps({
    fridgeHistory: {
        type: Array,
        default: () => [],
    },
    fridgeLoading: {
        type: Boolean,
        default: false,
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const parseDate = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date;
};

const sameDate = (left, right) => (
    left.getFullYear() === right.getFullYear()
    && left.getMonth() === right.getMonth()
    && left.getDate() === right.getDate()
);

const dayLabel = (date) => {
    if (!date) {
        return 'Без даты';
    }

    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (sameDate(date, today)) {
        return 'Сегодня';
    }

    if (sameDate(date, yesterday)) {
        return 'Вчера';
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
    });
};

const actionDate = (item) => parseDate(item.eaten_at || item.discarded_at || item.updated_at || item.expires_at);

const actionTime = (item) => {
    const date = actionDate(item);

    if (!date) {
        return '—';
    }

    return date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const groupedHistory = computed(() => {
    const groups = new Map();

    props.fridgeHistory.forEach((item) => {
        const date = actionDate(item);
        const label = dayLabel(date);

        if (!groups.has(label)) {
            groups.set(label, []);
        }

        groups.get(label).push({
            ...item,
            _actionTime: actionTime(item),
        });
    });

    return Array.from(groups.entries()).map(([label, items]) => ({ label, items }));
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 sm:px-5 sm:pt-5" :aria-busy="fridgeLoading">
        <div v-if="showHeading" class="mb-4 shrink-0">
            <h2 class="customer-heading text-balance text-xl leading-7 sm:text-2xl sm:leading-8">Моя история</h2>
            <p data-testid="history-panel-subtitle" class="customer-muted mt-0.5 text-pretty text-sm leading-5">Последние действия с блюдами.</p>
        </div>

        <div v-if="fridgeLoading" class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-5 pr-1">
            <Skeleton
                v-for="skeleton in orderSkeletonRows"
                :key="`history-skeleton-${skeleton}`"
                class="h-16 w-full rounded-xl bg-slate-100"
            />
        </div>

        <div
            v-else-if="fridgeHistory.length === 0"
            class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center"
        >
            <History aria-hidden="true" class="size-7 text-slate-300" />
            <p class="customer-title mt-3 text-balance text-base leading-5">Истории пока нет.</p>
            <p class="customer-muted mt-1 text-pretty text-sm leading-6">Когда вы отметите блюдо в холодильнике, оно появится здесь.</p>
        </div>

        <div data-testid="history-panel-scroll" v-else class="min-h-0 flex-1 space-y-5 overflow-x-hidden overflow-y-auto overscroll-contain pb-5 pr-1">
            <section v-for="group in groupedHistory" :key="group.label" aria-label="День истории питания">
                <h3 data-testid="history-panel-group-label" class="customer-meta mb-2 px-1 text-xs leading-4">{{ group.label }}</h3>
                <ul class="space-y-2.5">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        data-testid="history-panel-row"
                        class="customer-row-card px-3.5 py-3 shadow-sm sm:px-4"
                    >
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p data-testid="history-panel-title" class="customer-title line-clamp-2 break-words text-pretty text-[15px] leading-5">{{ item.title_snapshot }}</p>
                                <span
                                    data-testid="history-panel-status-chip"
                                    class="customer-badge mt-2 inline-flex h-auto min-h-6 px-2.5 py-1 text-xs leading-4"
                                >
                                    {{ fridgeStatusLabel(item.status) }}
                                </span>
                            </div>
                            <span
                                data-testid="history-panel-time"
                                class="customer-meta shrink-0 pt-0.5 text-xs leading-4 tabular-nums"
                            >
                                {{ item._actionTime }}
                            </span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
