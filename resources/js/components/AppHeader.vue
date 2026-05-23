<script setup>
import BrandLogo from '@/components/BrandLogo.vue';
import WeekStatus from '@/components/WeekStatus.vue';
import { Bell, ChevronDown, User } from 'lucide-vue-next';

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
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    totalPositions: {
        type: Number,
        default: 0,
    },
    displayUserName: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['open-auth', 'open-profile']);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-[#e7ecf6] bg-white/95 shadow-[0_8px_30px_rgba(21,39,75,0.04)] backdrop-blur">
        <div class="header-inner app-header-shell">
            <div class="flex min-w-0 items-center">
                <BrandLogo />
            </div>

            <WeekStatus
                :cycle="cycle"
                :weekly-deadline-label="weeklyDeadlineLabel"
                :is-open-for-ordering="isOpenForOrdering"
                :availability-label="availabilityLabel"
                :availability-description="availabilityDescription"
            />

            <div class="app-header-actions flex items-center gap-3">
                <button
                    v-if="isAuthenticated"
                    type="button"
                    class="relative grid h-11 w-11 place-items-center rounded-[13px] border border-[#e5ebf7] bg-white text-[#52617f] shadow-[0_8px_22px_rgba(34,58,104,0.05)] transition hover:border-[#cdd8ef] hover:text-[#174eff]"
                    aria-label="Уведомления"
                >
                    <Bell class="size-5" />
                    <span v-if="totalPositions" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-[#ff2e42] px-1 text-[10px] font-bold text-white">
                        {{ totalPositions }}
                    </span>
                </button>

                <button
                    v-if="isAuthenticated"
                    type="button"
                    class="inline-flex h-12 min-w-[190px] items-center gap-3 rounded-[999px] border border-[#e5ebf7] bg-white py-2 pl-3 pr-4 text-left shadow-[0_8px_22px_rgba(34,58,104,0.05)] transition hover:border-[#cdd8ef] hover:bg-[#f8faff]"
                    @click="emit('open-profile')"
                >
                    <div class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-[#f1f5ff] text-[#2459d9]">
                        <User class="size-5" />
                    </div>
                    <span class="min-w-0 flex-1 truncate text-[15px] font-black text-[#111827]">{{ displayUserName }}</span>
                    <ChevronDown class="size-4 text-[#66769f]" />
                </button>

                <button
                    v-else
                    type="button"
                    class="inline-flex h-12 min-w-[156px] items-center justify-center gap-3 rounded-[999px] border border-[#e5ebf7] bg-white px-5 text-[15px] font-black text-[#111827] shadow-[0_8px_22px_rgba(34,58,104,0.05)] transition hover:border-[#cdd8ef] hover:bg-[#f8faff]"
                    @click="emit('open-auth')"
                >
                    <div class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-[#f1f5ff] text-[#2459d9]">
                        <User class="size-5" />
                    </div>
                    Войти
                </button>
            </div>
        </div>
    </header>
</template>
