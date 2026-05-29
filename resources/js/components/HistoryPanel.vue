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
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4" :aria-busy="fridgeLoading">
        <div v-if="showHeading" class="mb-4 shrink-0">
            <h2 class="text-lg font-semibold text-slate-950">Моя история</h2>
            <p class="mt-0.5 text-sm text-slate-500">Последние действия с блюдами.</p>
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
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">Истории пока нет.</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Когда вы отметите блюдо в холодильнике, оно появится здесь.</p>
        </div>

        <div v-else class="min-h-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto overscroll-contain pb-5 pr-1 text-sm text-slate-700">
            <section v-for="group in groupedHistory" :key="group.label" aria-label="День истории питания">
                <div class="mb-2 flex items-center gap-2 px-1">
                    <h3 class="text-xs font-semibold text-slate-400">{{ group.label }}</h3>
                    <span class="h-px flex-1 bg-slate-200/80" />
                </div>
                <ul class="space-y-2">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        data-testid="history-panel-row"
                        class="rounded-xl border border-slate-200/80 bg-white px-3 py-2.5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="line-clamp-2 break-words text-sm font-medium text-slate-900">{{ item.title_snapshot }}</p>
                                <span
                                    data-testid="history-panel-status-chip"
                                    class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
                                >
                                    {{ fridgeStatusLabel(item.status) }}
                                </span>
                            </div>
                            <span
                                data-testid="history-panel-time"
                                class="shrink-0 pt-0.5 text-xs font-medium tabular-nums text-slate-500"
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
