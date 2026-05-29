<script setup>
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { CalendarDays } from 'lucide-vue-next';

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
    orderStatusText: {
        type: String,
        default: '',
    },
});

const cycleCaption = computed(() => props.cycle?.title || 'Текущая неделя');

const guestStatusText = computed(() => {
    if (!props.cycle) {
        return 'Приём заказов закрыт';
    }

    if (props.isOpenForOrdering) {
        return `Заказ открыт · Дедлайн: ${props.weeklyDeadlineLabel}`;
    }

    return 'Приём заказов закрыт';
});

const primaryStatusText = computed(() => props.orderStatusText || guestStatusText.value);
</script>

<template>
    <section
        v-if="loading"
        data-testid="week-status-loading"
        class="week-status rounded-2xl border border-slate-200/85 bg-white/95 px-4 py-3 shadow-[0_10px_30px_rgb(148_163_184/0.12)]"
        aria-busy="true"
        aria-label="Загрузка недельного цикла"
    >
        <div class="flex flex-col gap-2">
            <Skeleton class="h-4 w-44 max-w-full bg-slate-100" />
            <Skeleton class="h-5 w-72 max-w-full bg-slate-100" />
        </div>
    </section>

    <section
        v-else
        class="week-status rounded-2xl border border-slate-200/85 bg-white/95 px-4 py-3 shadow-[0_10px_30px_rgb(148_163_184/0.12)]"
        aria-label="Текущий недельный цикл"
    >
        <div class="flex min-w-0 items-center gap-2 text-xs font-medium text-slate-500">
            <CalendarDays aria-hidden="true" class="size-3.5 shrink-0 text-amber-700" />
            <p class="min-w-0 truncate">{{ cycleCaption }}</p>
        </div>

        <p class="mt-1 text-pretty text-sm font-semibold leading-5 text-slate-950 sm:text-base">
            {{ primaryStatusText }}
        </p>
        <p v-if="availabilityDescription" class="mt-1 text-xs text-slate-500">
            {{ availabilityDescription }}
        </p>
    </section>
</template>
