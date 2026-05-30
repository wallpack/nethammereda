<script setup>
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';

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

const fallbackStatusText = computed(() => {
    if (props.isOpenForOrdering) {
        return props.weeklyDeadlineLabel
            ? `Приём заказов открыт · до ${props.weeklyDeadlineLabel}`
            : 'Приём заказов открыт';
    }

    return 'Приём заказов закрыт';
});

const primaryStatusText = computed(() => props.orderStatusText || fallbackStatusText.value);
const statusDotClass = computed(() => props.isOpenForOrdering ? 'bg-blue-700' : 'bg-slate-400');
</script>

<template>
    <section
        v-if="loading"
        data-testid="week-status-loading"
        class="week-status rounded-[1.25rem] border border-slate-200/80 bg-white px-4 py-3 shadow-sm"
        aria-busy="true"
        aria-label="Загрузка статуса приёма заказов"
    >
        <Skeleton class="h-5 w-72 max-w-full rounded-full bg-[#f2f2f2]" />
    </section>

    <section
        v-else
        class="week-status rounded-[1.25rem] border border-slate-200/80 bg-white px-4 py-3 shadow-sm"
        aria-label="Статус приёма заказов"
    >
        <div class="flex min-h-5 min-w-0 items-center gap-2.5">
            <span class="size-2.5 shrink-0 rounded-full" :class="statusDotClass" aria-hidden="true" />
            <p class="min-w-0 truncate text-sm font-semibold text-slate-900 sm:text-[15px]">
                {{ primaryStatusText }}
            </p>
        </div>
    </section>
</template>
