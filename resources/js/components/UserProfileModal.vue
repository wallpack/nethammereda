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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice } from '@/lib/formatters';
import { BookOpen, Check, CheckCircle2, Heart, History, Link2, Loader2, LogOut, Refrigerator, ShoppingBag, UserRound, X } from 'lucide-vue-next';

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
    orderHistory: {
        type: Array,
        default: () => [],
    },
    orderHistoryLoading: {
        type: Boolean,
        default: false,
    },
    orderHistoryError: {
        type: String,
        default: '',
    },
    canRepeatHistory: {
        type: Boolean,
        default: false,
    },
    repeatActionLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'logout',
    'show-favorites',
    'show-catalog',
    'show-order',
    'show-fridge',
    'show-history',
    'save-full-name',
    'telegram-link',
    'telegram-open-bot',
    'repeat-order',
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
const activeTab = ref('profile');
const expandedHistoryOrderIds = ref(new Set());

const historyPositionsLabel = (count) => {
    if (count % 10 === 1 && count % 100 !== 11) {
        return 'блюдо';
    }

    if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
        return 'блюда';
    }

    return 'блюд';
};

const historyOrderDateLabel = (value) => {
    if (!value) {
        return 'Заказ';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Заказ';
    }

    return `Заказ от ${date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'long' })}`;
};

watch(
    () => [props.open, props.user?.full_name],
    () => {
        if (!props.open) {
            return;
        }

        fullName.value = props.user?.full_name ?? '';
        activeTab.value = 'profile';
        expandedHistoryOrderIds.value = new Set();
    },
    { immediate: true },
);

const historyPreviewCount = 4;
const historyOrderItems = (historyOrder) => {
    const items = Array.isArray(historyOrder?.items) ? historyOrder.items : [];

    if (expandedHistoryOrderIds.value.has(historyOrder.id)) {
        return items;
    }

    return items.slice(0, historyPreviewCount);
};

const hiddenHistoryItemsCount = (historyOrder) => {
    const total = Number(historyOrder?.items_count || 0);
    const hidden = total - historyPreviewCount;

    return hidden > 0 ? hidden : 0;
};

const isHistoryOrderExpanded = (historyOrder) => expandedHistoryOrderIds.value.has(historyOrder.id);

const toggleHistoryOrderExpanded = (historyOrder) => {
    const next = new Set(expandedHistoryOrderIds.value);

    if (next.has(historyOrder.id)) {
        next.delete(historyOrder.id);
    } else {
        next.add(historyOrder.id);
    }

    expandedHistoryOrderIds.value = next;
};

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
            <DialogContent
                data-testid="profile-modal-content"
                class="customer-app fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-1.5rem)] w-[min(calc(100%_-_1rem),38rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-[1.75rem] border border-slate-200/70 bg-white p-5 text-slate-900 shadow-2xl outline-none sm:max-h-[calc(100dvh-2rem)] sm:w-[min(calc(100%_-_2rem),38rem)] sm:p-6"
            >
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-3 top-3 size-10 rounded-full text-slate-400 hover:bg-slate-100 hover:text-[#404040]"
                        aria-label="Закрыть профиль"
                    >
                        <X aria-hidden="true" class="size-4.5" />
                    </Button>
                </DialogClose>

                <div data-testid="profile-modal-header" class="flex items-start gap-3.5 pr-12">
                    <div data-testid="profile-avatar" class="grid size-14 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                        <UserRound aria-hidden="true" class="size-7" />
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <DialogTitle
                            data-testid="profile-name"
                            class="customer-heading line-clamp-2 break-words text-balance text-xl leading-7"
                            :title="displayName"
                        >
                            {{ displayName }}
                        </DialogTitle>
                        <DialogDescription v-if="identifier" class="customer-muted mt-1 break-all text-pretty text-sm leading-5">
                            {{ identifier }}
                        </DialogDescription>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-1 rounded-full bg-[#f2f4f7] p-1" data-testid="profile-tabs">
                    <button
                        type="button"
                        class="h-10 rounded-full px-3 text-sm font-semibold transition-[background-color,color,box-shadow] duration-150"
                        :class="activeTab === 'profile' ? 'bg-white text-[#404040] shadow-sm' : 'customer-muted hover:text-[#404040]'"
                        data-testid="profile-tab-main"
                        @click="activeTab = 'profile'"
                    >
                        Профиль
                    </button>
                    <button
                        type="button"
                        class="h-10 rounded-full px-3 text-sm font-semibold transition-[background-color,color,box-shadow] duration-150"
                        :class="activeTab === 'orders' ? 'bg-white text-[#404040] shadow-sm' : 'customer-muted hover:text-[#404040]'"
                        data-testid="profile-tab-ordered"
                        @click="activeTab = 'orders'"
                    >
                        Уже заказывали
                    </button>
                </div>

                <div v-if="activeTab === 'profile'" class="mt-4 grid gap-3">
                    <form data-testid="profile-form-card" class="customer-soft-card grid gap-3 p-4" @submit.prevent="saveFullName">
                        <label class="grid gap-2">
                            <span class="customer-label">ФИО</span>
                            <Input
                                v-model="fullName"
                                data-testid="profile-full-name-input"
                                maxlength="255"
                                placeholder="Например: Чертова Е.Н."
                                class="customer-input h-12 px-4 text-base focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                            />
                        </label>
                        <p class="customer-muted text-xs leading-5">
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
                            class="customer-cta h-11 w-full text-sm sm:w-auto sm:px-5"
                            :disabled="profileSaving"
                        >
                            <Loader2 v-if="profileSaving" aria-hidden="true" class="size-4 animate-spin" />
                            Сохранить ФИО
                        </Button>
                    </form>

                    <Button
                        type="button"
                        variant="outline"
                        class="customer-row-card h-auto min-h-14 justify-between px-4 py-3 text-sm shadow-none hover:bg-slate-50"
                        data-testid="profile-favorites-action"
                        @click="emit('show-favorites')"
                    >
                        <span class="inline-flex min-w-0 items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600">
                                <Heart aria-hidden="true" class="size-4.5" />
                            </span>
                            <span class="grid min-w-0 text-left">
                                <span class="customer-title text-sm leading-5">Избранное</span>
                                <span class="customer-muted text-xs leading-4">Сохранённые блюда</span>
                            </span>
                        </span>
                        <span class="customer-price tabular-nums">{{ favoritesCount || '' }}</span>
                    </Button>

                    <div data-testid="profile-quick-actions" class="customer-soft-card p-4">
                        <p class="customer-title text-sm leading-5">Быстрые действия</p>
                        <p class="customer-muted mt-1 text-xs leading-5">Переходите между каталогом и личными разделами.</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 justify-start rounded-full border-slate-200 bg-white px-3 text-xs font-semibold text-[#595959] shadow-none hover:bg-slate-50"
                                data-testid="profile-catalog-action"
                                @click="emit('show-catalog')"
                            >
                                <BookOpen aria-hidden="true" class="size-4 text-slate-500" />
                                Каталог
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 justify-start rounded-full border-slate-200 bg-white px-3 text-xs font-semibold text-[#595959] shadow-none hover:bg-slate-50"
                                data-testid="profile-order-action"
                                @click="emit('show-order')"
                            >
                                <ShoppingBag aria-hidden="true" class="size-4 text-slate-500" />
                                Мой заказ
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 justify-start rounded-full border-slate-200 bg-white px-3 text-xs font-semibold text-[#595959] shadow-none hover:bg-slate-50"
                                data-testid="profile-fridge-action"
                                @click="emit('show-fridge')"
                            >
                                <Refrigerator aria-hidden="true" class="size-4 text-slate-500" />
                                Холодильник
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 justify-start rounded-full border-slate-200 bg-white px-3 text-xs font-semibold text-[#595959] shadow-none hover:bg-slate-50"
                                data-testid="profile-history-action"
                                @click="emit('show-history')"
                            >
                                <History aria-hidden="true" class="size-4 text-slate-500" />
                                История
                            </Button>
                        </div>
                    </div>

                    <div data-testid="profile-telegram-card" class="customer-soft-card bg-[#f8fbff] p-4">
                        <div class="flex items-start gap-3">
                            <div class="relative mt-0.5 shrink-0">
                                <span class="grid size-10 place-items-center rounded-2xl bg-sky-100 text-sky-600">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 fill-current">
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="customer-title text-sm leading-5">Telegram-бот</p>
                                    <span
                                        v-if="telegramLinked"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"
                                        data-testid="profile-telegram-linked"
                                    >
                                        <CheckCircle2 aria-hidden="true" class="size-3.5" />
                                        Подключён
                                    </span>
                                </div>
                                <p
                                    v-if="telegramLinked"
                                    class="customer-body mt-1 text-xs leading-5"
                                    data-testid="profile-telegram-linked-text"
                                >
                                    Подключён. Уведомления о заказах активны.
                                </p>
                                <p
                                    v-else
                                    class="customer-muted mt-1 text-xs leading-5"
                                    data-testid="profile-telegram-unlinked"
                                >
                                    Привяжите Telegram, чтобы получать уведомления о заказах.
                                </p>
                                <p
                                    v-if="telegramLinked && telegramIdentity"
                                    class="customer-muted mt-1 text-xs leading-5"
                                    data-testid="profile-telegram-identity"
                                >
                                    {{ telegramIdentity }}
                                </p>
                                <p
                                    v-else
                                    class="customer-muted mt-1 text-xs leading-5"
                                    data-testid="profile-telegram-helper"
                                >
                                    Привязка занимает несколько секунд.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="!telegramLinked && !telegramLinkAvailable"
                            class="customer-title mt-3 text-sm leading-5"
                            data-testid="profile-telegram-unavailable"
                        >
                            Привязка временно недоступна.
                        </p>
                        <p
                            v-if="!telegramLinked && !telegramLinkAvailable"
                            class="customer-muted mt-1 text-xs leading-5"
                            data-testid="profile-telegram-unavailable-hint"
                        >
                            Обратитесь к администратору.
                        </p>
                        <p
                            v-if="!telegramLinked && telegramLinkAvailable && telegramError"
                            class="mt-3 text-xs font-semibold text-rose-600"
                            data-testid="profile-telegram-error"
                        >
                            {{ telegramError }}
                        </p>

                        <Button
                            v-if="telegramLinked"
                            type="button"
                            variant="outline"
                            class="mt-3 h-10 w-full justify-center rounded-full border-slate-200 bg-white px-4 text-xs font-semibold text-[#595959] shadow-none hover:bg-white/90 sm:w-auto"
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
                            class="mt-3 h-10 w-full justify-center rounded-full bg-sky-600 px-4 text-xs font-semibold text-white hover:bg-sky-700 sm:w-auto"
                            data-testid="profile-telegram-link"
                            :disabled="telegramLoading"
                            @click="emit('telegram-link')"
                        >
                            <span class="inline-flex w-4 items-center justify-center">
                                <Loader2 v-if="telegramLoading" aria-hidden="true" class="size-4 animate-spin" />
                                <Link2 v-else aria-hidden="true" class="size-4" />
                            </span>
                            <span class="inline-block min-w-[10.5rem] text-left">
                                {{ telegramLoading ? 'Создаём ссылку...' : 'Привязать Telegram' }}
                            </span>
                        </Button>
                    </div>
                </div>

                <div v-else class="mt-4" data-testid="profile-orders-tab-panel">
                    <h3 class="customer-title text-base leading-6">Уже заказывали</h3>

                    <div v-if="orderHistoryLoading" class="mt-3 space-y-2">
                        <div class="h-16 animate-pulse rounded-2xl bg-slate-100" />
                        <div class="h-16 animate-pulse rounded-2xl bg-slate-100" />
                    </div>

                    <Alert
                        v-else-if="orderHistoryError"
                        variant="destructive"
                        class="mt-3 rounded-xl border-red-200 bg-red-50 text-red-700"
                    >
                        <AlertDescription>{{ orderHistoryError }}</AlertDescription>
                    </Alert>

                    <div
                        v-else-if="!orderHistory.length"
                        class="customer-soft-card mt-3 px-4 py-4 text-sm"
                        data-testid="profile-order-history-empty"
                    >
                        <p class="customer-title text-sm">Вы ещё не оформляли заказы.</p>
                        <p class="customer-muted mt-1 text-xs leading-5">После первого заказа он появится здесь.</p>
                    </div>

                    <div v-else class="mt-3 max-h-[20rem] space-y-2 overflow-y-auto pr-1">
                        <article
                            v-for="historyOrder in orderHistory"
                            :key="historyOrder.id"
                            class="customer-row-card p-3"
                        >
                            <p class="customer-title text-sm leading-5">{{ historyOrderDateLabel(historyOrder.submitted_at) }}</p>
                            <p class="customer-muted mt-0.5 text-xs leading-5">
                                {{ historyOrder.items_count }} {{ historyPositionsLabel(Number(historyOrder.items_count || 0)) }}
                                · <span class="customer-price">{{ formatPrice(historyOrder.total_price ?? 0) }}</span>
                            </p>
                            <ul data-testid="profile-history-order-items" class="customer-body mt-2 space-y-1 text-xs leading-5">
                                <li
                                    v-for="item in historyOrderItems(historyOrder)"
                                    :key="`${historyOrder.id}-${item.id}`"
                                    class="truncate"
                                >
                                    {{ item.title }} ×{{ item.quantity }}
                                </li>
                            </ul>
                            <Button
                                v-if="hiddenHistoryItemsCount(historyOrder)"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="mt-1 h-7 rounded-full px-2.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-[#404040]"
                                data-testid="profile-history-expand-button"
                                @click="toggleHistoryOrderExpanded(historyOrder)"
                            >
                                {{ isHistoryOrderExpanded(historyOrder) ? 'Свернуть' : `Показать всё (${hiddenHistoryItemsCount(historyOrder)})` }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                class="customer-cta mt-2 h-9 px-4 text-xs disabled:bg-slate-200 disabled:text-slate-500"
                                data-testid="profile-repeat-order-button"
                                :disabled="!canRepeatHistory || !historyOrder.can_repeat || repeatActionLoading"
                                @click="emit('repeat-order', historyOrder)"
                            >
                                <Loader2 v-if="repeatActionLoading" aria-hidden="true" class="size-3 animate-spin" />
                                Повторить заказ
                            </Button>
                        </article>
                    </div>

                    <p v-if="!canRepeatHistory" class="customer-muted mt-3 text-xs leading-5" data-testid="profile-repeat-closed-hint">
                        Повторить заказ можно, когда открыт приём заказов.
                    </p>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    data-testid="profile-logout-button"
                    class="mt-4 h-11 w-full justify-start rounded-full px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                    @click="emit('logout')"
                >
                    <LogOut aria-hidden="true" class="size-4.5" />
                    Выйти
                </Button>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
