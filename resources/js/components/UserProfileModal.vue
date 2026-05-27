<script setup>
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Check, CheckCircle2, Heart, History, Link2, Loader2, LogOut, Refrigerator, ShoppingBag, UserRound, X } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    favoritesCount: {
        type: Number,
        default: 0,
    },
    profileSaving: {
        type: Boolean,
        default: false,
    },
    profileError: {
        type: String,
        default: '',
    },
    telegramLinked: {
        type: Boolean,
        default: false,
    },
    telegramLinkAvailable: {
        type: Boolean,
        default: false,
    },
    telegramLoading: {
        type: Boolean,
        default: false,
    },
    telegramError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'close',
    'logout',
    'show-favorites',
    'show-order',
    'show-fridge',
    'show-history',
    'save-full-name',
    'telegram-link',
    'telegram-open-bot',
]);

const displayName = computed(() => {
    return props.user?.full_name || props.user?.name || props.user?.first_name || props.user?.email || 'Пользователь';
});

const identifier = computed(() => props.user?.email || props.user?.phone || props.user?.telegram_id || '');
const telegramIdentity = computed(() => {
    if (!props.telegramLinked) {
        return '';
    }

    const telegramUsername = props.user?.telegram_username;

    if (telegramUsername) {
        return telegramUsername.startsWith('@') ? telegramUsername : `@${telegramUsername}`;
    }

    const telegramId = props.user?.telegram_id;

    if (!telegramId) {
        return '';
    }

    return `ID: ${telegramId}`;
});
const fullName = ref('');

watch(
    () => [props.open, props.user?.full_name],
    () => {
        if (!props.open) {
            return;
        }

        fullName.value = props.user?.full_name ?? '';
    },
    { immediate: true },
);

const closeWhenChanged = (open) => {
    if (!open) {
        emit('close');
    }
};

const saveFullName = () => {
    emit('save-full-name', fullName.value);
};
</script>

<template>
    <DialogRoot :open="open" @update:open="closeWhenChanged">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-slate-950/45" />
            <DialogContent class="fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[min(calc(100%_-_2rem),30rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl outline-none sm:p-7">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-3 top-3 size-11 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Закрыть профиль"
                    >
                        <X aria-hidden="true" class="size-5" />
                    </Button>
                </DialogClose>

                <div class="flex items-start gap-4 pr-12">
                    <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">
                        <UserRound aria-hidden="true" class="size-7" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle
                            data-testid="profile-name"
                            class="line-clamp-2 break-words text-balance text-xl font-semibold leading-7 text-slate-950"
                            :title="displayName"
                        >
                            {{ displayName }}
                        </DialogTitle>
                        <DialogDescription v-if="identifier" class="mt-1 break-all text-pretty text-sm leading-5 text-slate-500">
                            {{ identifier }}
                        </DialogDescription>
                    </div>
                </div>

                <div class="mt-6 grid gap-2">
                    <form class="mb-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4" @submit.prevent="saveFullName">
                        <label class="grid gap-2">
                            <span class="text-sm font-medium text-slate-700">ФИО</span>
                            <Input
                                v-model="fullName"
                                data-testid="profile-full-name-input"
                                maxlength="255"
                                placeholder="Например: Чертова Е.Н."
                                class="h-11 rounded-xl border-slate-200 bg-white px-3 text-slate-900 placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                            />
                        </label>
                        <p class="text-xs leading-5 text-slate-500">
                            Укажите ФИО в формате: Фамилия и инициалы. Например: Иванов И.И.
                        </p>
                        <Alert
                            v-if="profileError"
                            variant="destructive"
                            class="rounded-xl border-red-200 bg-red-50 text-red-700"
                            role="alert"
                        >
                            <AlertDescription>{{ profileError }}</AlertDescription>
                        </Alert>
                        <Button
                            type="submit"
                            data-testid="profile-save-full-name"
                            class="h-10 rounded-xl bg-blue-700 text-sm font-semibold text-white hover:bg-blue-800"
                            :disabled="profileSaving"
                        >
                            <Loader2 v-if="profileSaving" aria-hidden="true" class="size-4 animate-spin" />
                            Сохранить ФИО
                        </Button>
                    </form>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-between rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-favorites-action"
                        @click="emit('show-favorites')"
                    >
                        <span class="inline-flex items-center gap-3">
                            <Heart aria-hidden="true" class="size-5 text-rose-600" />
                            Избранное
                        </span>
                        <span class="tabular-nums text-slate-500">{{ favoritesCount || '' }}</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-order-action"
                        @click="emit('show-order')"
                    >
                        <span class="inline-flex items-center gap-3">
                            <ShoppingBag aria-hidden="true" class="size-5 text-blue-700" />
                            Мой заказ
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-fridge-action"
                        @click="emit('show-fridge')"
                    >
                        <Refrigerator aria-hidden="true" class="size-5 text-blue-700" />
                        Холодильник
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-history-action"
                        @click="emit('show-history')"
                    >
                        <History aria-hidden="true" class="size-5 text-blue-700" />
                        История питания
                    </Button>

                    <div class="rounded-2xl border border-sky-100 bg-sky-50/40 p-4">
                        <div class="flex items-start gap-3">
                            <div class="relative mt-0.5">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-sky-100 text-sky-600">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-current">
                                        <path d="M21.2 4.6 18.2 18.8c-.2 1-1 1.2-1.8.8l-4.6-3.4-2.2 2.1c-.2.2-.4.4-.8.4l.3-4.7 8.7-7.9c.4-.3-.1-.5-.5-.2l-10.8 6.8-4.6-1.4c-1-.3-1-1 .2-1.4L19.5 3c.8-.3 2 .2 1.7 1.6Z" />
                                    </svg>
                                </span>
                                <span
                                    v-if="telegramLinked"
                                    class="absolute -bottom-1 -right-1 grid size-4 place-items-center rounded-full bg-emerald-500 text-white"
                                >
                                    <Check aria-hidden="true" class="size-3" />
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900">Telegram-бот</p>
                                <p
                                    v-if="telegramLinked"
                                    class="mt-1 text-sm text-slate-700"
                                    data-testid="profile-telegram-linked-text"
                                >
                                    Telegram подключён. Вы будете получать уведомления и сможете быстро открыть меню.
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-sm text-slate-600"
                                    data-testid="profile-telegram-unlinked"
                                >
                                    Получайте уведомления о заказах и быстро открывайте меню прямо из Telegram.
                                </p>
                                <p
                                    v-if="telegramLinked"
                                    class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                    data-testid="profile-telegram-linked"
                                >
                                    <CheckCircle2 aria-hidden="true" class="size-3.5" />
                                    Подключён
                                </p>
                                <p
                                    v-if="telegramLinked && telegramIdentity"
                                    class="mt-2 text-xs text-slate-500"
                                    data-testid="profile-telegram-identity"
                                >
                                    {{ telegramIdentity }}
                                </p>
                                <p
                                    v-else
                                    class="mt-2 text-xs text-slate-500"
                                    data-testid="profile-telegram-helper"
                                >
                                    Привязка занимает несколько секунд.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="!telegramLinked && !telegramLinkAvailable"
                            class="mt-3 text-sm font-medium text-slate-700"
                            data-testid="profile-telegram-unavailable"
                        >
                            Привязка временно недоступна.
                        </p>
                        <p
                            v-if="!telegramLinked && !telegramLinkAvailable"
                            class="mt-1 text-xs text-slate-500"
                            data-testid="profile-telegram-unavailable-hint"
                        >
                            Обратитесь к администратору.
                        </p>
                        <p
                            v-if="!telegramLinked && telegramLinkAvailable && telegramError"
                            class="mt-2 text-xs text-rose-600"
                            data-testid="profile-telegram-error"
                        >
                            {{ telegramError }}
                        </p>

                        <Button
                            v-if="telegramLinked"
                            type="button"
                            variant="outline"
                            class="mt-3 h-10 w-full justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-white/90 sm:w-auto"
                            data-testid="profile-telegram-open-bot"
                            :disabled="!telegramLinkAvailable"
                            @click="emit('telegram-open-bot')"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 fill-current text-sky-600">
                                <path d="M21.2 4.6 18.2 18.8c-.2 1-1 1.2-1.8.8l-4.6-3.4-2.2 2.1c-.2.2-.4.4-.8.4l.3-4.7 8.7-7.9c.4-.3-.1-.5-.5-.2l-10.8 6.8-4.6-1.4c-1-.3-1-1 .2-1.4L19.5 3c.8-.3 2 .2 1.7 1.6Z" />
                            </svg>
                            Открыть Telegram
                        </Button>
                        <Button
                            v-else-if="telegramLinkAvailable"
                            type="button"
                            class="mt-3 h-10 w-full justify-start rounded-xl bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700 sm:w-auto"
                            data-testid="profile-telegram-link"
                            :disabled="telegramLoading"
                            @click="emit('telegram-link')"
                        >
                            <span class="inline-flex w-4 items-center justify-center">
                                <Loader2 v-if="telegramLoading" aria-hidden="true" class="size-4 animate-spin" />
                                <Link2 v-else aria-hidden="true" class="size-4" />
                            </span>
                            <span class="inline-block min-w-[12rem] text-left">
                                {{ telegramLoading ? 'Создаём ссылку...' : 'Привязать Telegram' }}
                            </span>
                        </Button>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    class="mt-5 h-12 w-full justify-start rounded-xl px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                    @click="emit('logout')"
                >
                    <LogOut aria-hidden="true" class="size-5" />
                    Выйти
                </Button>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
