<script setup>
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { CalendarDays, CheckCircle2, Clock3, PackageCheck } from 'lucide-vue-next';

const props = defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    cycle: {
        type: Object,
        default: null,
    },
    weeklyDeadlineLabel: {
        type: String,
        required: true,
    },
    isOpenForOrdering: {
        type: Boolean,
        default: false,
    },
    availabilityLabel: {
        type: String,
        required: true,
    },
    availabilityDescription: {
        type: String,
        default: '',
    },
});

const status = computed(() => props.cycle?.status ?? null);
const deadlinePassed = computed(() => Boolean(props.cycle?.deadline_passed));

const title = computed(() => {
    if (!props.cycle) {
        return 'Недельный цикл не создан';
    }

    if (props.isOpenForOrdering) {
        return 'Прием заказов открыт';
    }

    if (status.value === 'open' && deadlinePassed.value) {
        return 'Прием заказов завершен';
    }

    const titles = {
        sent_to_supplier: 'Заказ отправлен поставщику',
        delivered: 'Доставка отмечена',
        closed: 'Прием заказов закрыт',
        archived: 'Неделя завершена',
        draft: 'Меню готовится',
    };

    return titles[status.value] ?? 'Текущий недельный цикл';
});

const description = computed(() => {
    if (!props.cycle) {
        return 'Меню появится после создания цикла администратором.';
    }

    if (status.value === 'delivered') {
        return 'Проверьте холодильник.';
    }

    if (status.value === 'sent_to_supplier') {
        return 'Ваш выбор зафиксирован, поставщик уже получил сводный заказ.';
    }

    if (status.value === 'open' && deadlinePassed.value) {
        return 'Прием заказов завершен. Изменить заказ для этой недели больше нельзя.';
    }

    return props.availabilityDescription || 'Следите за состоянием заказа на этой неделе.';
});

const statusClasses = computed(() => {
    if (!props.cycle) {
        return 'border-slate-200 bg-white text-slate-600';
    }

    if (props.isOpenForOrdering) {
        return 'border-emerald-100 bg-white text-emerald-700';
    }

    if (status.value === 'delivered') {
        return 'border-blue-100 bg-white text-blue-700';
    }

    return 'border-amber-100 bg-white text-amber-800';
});

const statusIcon = computed(() => {
    if (status.value === 'delivered') {
        return PackageCheck;
    }

    if (props.isOpenForOrdering) {
        return CheckCircle2;
    }

    return Clock3;
});

const statusLabel = computed(() => {
    if (!props.cycle) {
        return 'Нет цикла';
    }

    if (status.value === 'delivered') {
        return 'Доставлен';
    }

    return props.availabilityLabel;
});

const deadlineLabel = computed(() => props.cycle ? props.weeklyDeadlineLabel : 'Пока не задан');
</script>

<template>
    <section
        v-if="loading"
        data-testid="week-status-loading"
        class="week-status grid gap-5 rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-7 sm:py-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-center"
        aria-busy="true"
        aria-label="Загрузка недельного цикла"
    >
        <div class="space-y-3">
            <Skeleton class="h-4 w-44 bg-slate-100" />
            <Skeleton class="h-8 w-72 max-w-full bg-slate-100" />
            <Skeleton class="h-5 w-full max-w-md bg-slate-100" />
        </div>
        <Skeleton class="h-28 rounded-2xl bg-slate-100" />
    </section>

    <section
        v-else
        class="week-status grid gap-6 rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-7 sm:py-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-center"
        aria-label="Текущий недельный цикл"
    >
        <div class="min-w-0">
            <p class="inline-flex items-center gap-2 text-sm font-medium text-slate-500">
                <CalendarDays aria-hidden="true" class="size-4 text-blue-700" />
                {{ cycle ? cycle.title : 'Текущая неделя' }}
            </p>
            <h1 class="mt-2 text-balance text-2xl font-semibold leading-tight text-slate-950 sm:text-3xl">
                {{ title }}
            </h1>
            <p class="mt-2 max-w-[65ch] text-pretty text-sm leading-6 text-slate-600 sm:text-base">
                {{ description }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4">
            <p class="text-xs font-medium text-slate-500">Дедлайн заказа</p>
            <p class="mt-1.5 text-lg font-semibold tabular-nums text-slate-950">{{ deadlineLabel }}</p>
            <Badge
                variant="outline"
                class="mt-3 inline-flex h-7 items-center justify-center gap-1.5 rounded-lg px-2.5 text-xs font-medium"
                :class="statusClasses"
            >
                <component :is="statusIcon" aria-hidden="true" class="size-3.5" />
                {{ statusLabel }}
            </Badge>
        </div>
    </section>
</template>
