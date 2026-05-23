<script setup>
import { Badge } from '@/components/ui/badge';
import { CalendarRange } from 'lucide-vue-next';

defineProps({
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
</script>

<template>
    <div class="app-header-meta hidden min-w-[420px] items-center justify-between rounded-[12px] border border-[#e5ebf7] bg-white px-5 py-2.5 text-sm shadow-[0_10px_28px_rgba(34,58,104,0.05)] lg:flex">
        <div class="flex items-center gap-3">
            <span class="grid h-8 w-8 place-items-center rounded-full border border-[#dfe6f3] text-[#2459d9]">
                <CalendarRange class="size-4" />
            </span>
            <div>
                <p class="font-bold leading-5 text-[#111827]">{{ cycle ? cycle.title : 'Недельный цикл не создан' }}</p>
                <p class="text-[12px] leading-4 text-[#66769f]">{{ availabilityDescription || `Дедлайн заказа: ${weeklyDeadlineLabel}` }}</p>
            </div>
        </div>

        <Badge
            :class="isOpenForOrdering ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-red-100 bg-red-50 text-red-600'"
            class="h-8 rounded-[10px] px-3 text-[12px] font-bold"
            variant="outline"
        >
            <span class="mr-2 h-2 w-2 rounded-full" :class="isOpenForOrdering ? 'bg-emerald-500' : 'bg-red-500'"></span>
            {{ availabilityLabel }}
        </Badge>
    </div>
</template>
