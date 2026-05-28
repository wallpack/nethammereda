<script setup>
import { ref, watch } from 'vue';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    initialValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['save']);
const fullName = ref('');

watch(
    () => [props.open, props.initialValue],
    () => {
        if (!props.open) {
            return;
        }

        fullName.value = props.initialValue;
    },
    { immediate: true },
);

const submit = () => {
    emit('save', fullName.value);
};
</script>

<template>
    <DialogRoot :open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[120] bg-slate-950/60" />
            <DialogContent
                data-testid="required-full-name-modal"
                class="fixed left-1/2 top-1/2 z-[130] w-[min(calc(100%_-_1.5rem),26rem)] -translate-x-1/2 -translate-y-1/2 rounded-3xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl outline-none sm:p-7"
                @escape-key-down.prevent
                @pointer-down-outside.prevent
                @interact-outside.prevent
            >
                <DialogTitle
                    data-testid="required-full-name-title"
                    class="text-center text-xl font-semibold leading-7 text-slate-950"
                >
                    Введите ФИО
                </DialogTitle>
                <p
                    data-testid="required-full-name-example"
                    class="mt-2 text-center text-sm leading-6 text-slate-500"
                >
                    Например: Иванов И.И.
                </p>

                <form class="mt-5 space-y-3" @submit.prevent="submit">
                    <Input
                        v-model="fullName"
                        data-testid="required-full-name-input"
                        maxlength="120"
                        placeholder="Иванов И.И."
                        class="h-11 rounded-xl border-slate-200 bg-white px-3 text-center text-slate-900 placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                    />
                    <p
                        v-if="error"
                        data-testid="required-full-name-error"
                        class="text-center text-sm font-medium text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <Button
                        type="submit"
                        data-testid="required-full-name-save"
                        class="h-11 w-full rounded-xl bg-blue-700 text-sm font-semibold text-white hover:bg-blue-800"
                        :disabled="saving"
                    >
                        <Loader2 v-if="saving" aria-hidden="true" class="size-4 animate-spin" />
                        Сохранить
                    </Button>
                </form>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
