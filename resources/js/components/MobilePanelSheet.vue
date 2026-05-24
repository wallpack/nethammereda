<script setup>
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    closeLabel: {
        type: String,
        required: true,
    },
    testId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const updateOpen = (open) => {
    if (!open) {
        emit('close');
    }
};
</script>

<template>
    <DialogRoot :open="props.open" @update:open="updateOpen">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-40 bg-slate-950/45 data-[state=closed]:opacity-0 data-[state=open]:opacity-100 xl:hidden"
            />
            <DialogContent
                :data-testid="testId"
                class="safe-panel-bottom fixed inset-x-0 bottom-0 z-50 flex max-h-[calc(100dvh-0.75rem)] flex-col overflow-hidden rounded-t-3xl border border-slate-200 bg-white text-slate-900 shadow-xl outline-none xl:hidden"
            >
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 pb-4 pt-5 sm:px-5">
                    <div class="min-w-0">
                        <DialogTitle class="text-balance text-lg font-semibold text-slate-950">
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription class="mt-1 text-pretty text-sm text-slate-500">
                            {{ description }}
                        </DialogDescription>
                    </div>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-11 shrink-0 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                            :aria-label="closeLabel"
                        >
                            <X aria-hidden="true" class="size-5" />
                        </Button>
                    </DialogClose>
                </div>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <slot />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
