<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthModal from '@/components/AuthModal.vue';
import UserProfileModal from '@/components/UserProfileModal.vue';
import {
    Bell,
    CalendarRange,
    ChevronDown,
    Clock3,
    Croissant,
    Heart,
    LayoutGrid,
    Loader2,
    Minus,
    Plus,
    Refrigerator,
    Salad,
    Search,
    SlidersHorizontal,
    Soup,
    ShoppingCart,
    Trash2,
    User,
    UtensilsCrossed,
    X,
} from 'lucide-vue-next';

const token = ref(localStorage.getItem('lunch_mvp_token') ?? sessionStorage.getItem('lunch_mvp_token') ?? '');
const me = ref(null);
const cycle = ref(null);
const categories = ref([]);
const items = ref([]);
const order = ref(null);
const fridgeItems = ref([]);
const fridgeHistory = ref([]);

const loading = ref(false);
const authLoading = ref(false);
const actionLoading = ref(false);
const fridgeLoading = ref(false);

const search = ref('');
const selectedCategory = ref(null);
const email = ref('');
const password = ref('');
const rememberMe = ref(Boolean(localStorage.getItem('lunch_mvp_token')));
const showPassword = ref(false);
const activeSidebarTab = ref('order');
const authError = ref('');
const isAuthModalOpen = ref(false);
const authModalMessage = ref('');
const isProfileModalOpen = ref(false);
const profileNotice = ref('');
const favoriteIds = ref(new Set());

const error = ref('');
const info = ref('');

const menuSkeletonRows = Array.from({ length: 6 }, (_, index) => index + 1);
const orderSkeletonRows = Array.from({ length: 3 }, (_, index) => index + 1);
const isAuthenticated = computed(() => Boolean(token.value && me.value));
const displayUserName = computed(() => {
    return me.value?.name || me.value?.full_name || me.value?.first_name || me.value?.email || 'Пользователь';
});

const api = async (path, options = {}) => {
    const headers = {
        Accept: 'application/json',
        ...(options.headers ?? {}),
    };

    if (token.value) {
        headers.Authorization = `Bearer ${token.value}`;
    }

    const payload = {
        method: options.method ?? 'GET',
        headers,
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload.body = JSON.stringify(options.body);
    }

    const response = await fetch(`/api${path}`, payload);
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message ?? 'Ошибка запроса');
    }

    return data;
};

const loadMe = async () => {
    const data = await api('/me');
    me.value = data.data;
};

const loadFridgeData = async () => {
    fridgeLoading.value = true;

    try {
        const [activeResponse, historyResponse] = await Promise.all([
            api('/my-fridge'),
            api('/my-fridge/history'),
        ]);

        fridgeItems.value = activeResponse.data ?? [];
        fridgeHistory.value = historyResponse.data ?? [];
    } finally {
        fridgeLoading.value = false;
    }
};

const loadCatalogData = async () => {
    const [cycleResponse, categoriesResponse, itemsResponse] = await Promise.all([
        api('/current-cycle'),
        api('/menu/categories'),
        api('/menu/items'),
    ]);

    cycle.value = cycleResponse.data;
    categories.value = categoriesResponse.data ?? [];
    items.value = itemsResponse.data ?? [];
};

const loadProtectedData = async () => {
    const [orderResponse] = await Promise.all([
        api('/my-order'),
        loadFridgeData(),
    ]);

    cycle.value = orderResponse.data?.cycle ?? cycle.value;
    order.value = orderResponse.data?.order ?? null;
};

const loadData = async () => {
    loading.value = true;
    error.value = '';

    try {
        await loadCatalogData();

        if (token.value) {
            await loadProtectedData();
        } else {
            order.value = null;
            fridgeItems.value = [];
            fridgeHistory.value = [];
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
};

const hasTelegramInitData = () => {
    return Boolean(window.Telegram?.WebApp?.initData);
};

const authWithTelegram = async () => {
    const initData = window.Telegram?.WebApp?.initData;

    if (!initData) {
        return false;
    }

    const response = await api('/auth/telegram', {
        method: 'POST',
        body: { init_data: initData },
    });

    token.value = response.data.token;

    return true;
};

const authWithPassword = async () => {
    const response = await api('/auth/login', {
        method: 'POST',
        body: {
            email: email.value,
            password: password.value,
        },
    });

    token.value = response.data.token;
    me.value = response.data.user ?? me.value;
};

const clearAuth = () => {
    token.value = '';
    me.value = null;
    order.value = null;
    fridgeItems.value = [];
    fridgeHistory.value = [];
    activeSidebarTab.value = 'order';
    favoriteIds.value = new Set();
    profileNotice.value = '';
};

const openAuthModal = (message = '') => {
    authModalMessage.value = message;
    authError.value = '';
    error.value = '';
    isAuthModalOpen.value = true;
};

const closeAuthModal = () => {
    isAuthModalOpen.value = false;
    authModalMessage.value = '';
    authError.value = '';
};

const openProfileModal = () => {
    profileNotice.value = '';
    isProfileModalOpen.value = true;
};

const closeProfileModal = () => {
    isProfileModalOpen.value = false;
    profileNotice.value = '';
};

const showProfileNotice = (message) => {
    profileNotice.value = message;
};

const ensureAuth = async () => {
    if (token.value) {
        try {
            await loadMe();
            return true;
        } catch {
            clearAuth();
        }
    }

    if (hasTelegramInitData()) {
        try {
            const success = await authWithTelegram();
            if (success) {
                await loadMe();
                return true;
            }
        } catch (e) {
            error.value = `Ошибка Telegram-авторизации: ${e.message}`;
        }
    }

    return false;
};

const loginFromWeb = async () => {
    authLoading.value = true;
    authError.value = '';
    info.value = '';

    try {
        await authWithPassword();
        await loadMe();
        await loadData();
        activeSidebarTab.value = 'order';
        password.value = '';
        closeAuthModal();
    } catch (e) {
        authError.value = e.message;
    } finally {
        authLoading.value = false;
    }
};

const loginFromTelegram = async () => {
    authLoading.value = true;
    authError.value = '';
    info.value = '';

    try {
        const success = await authWithTelegram();
        if (!success) {
            authError.value = 'Откройте страницу через кнопку /menu в Telegram.';
            return;
        }

        await loadMe();
        await loadData();
        activeSidebarTab.value = 'order';
        closeAuthModal();
    } catch (e) {
        authError.value = e.message;
    } finally {
        authLoading.value = false;
    }
};

const logout = async () => {
    if (token.value) {
        try {
            await api('/auth/logout', { method: 'POST' });
        } catch {
        }
    }

    clearAuth();
    closeProfileModal();
    error.value = '';
    info.value = '';
    authError.value = '';
};

const isOpenForOrdering = computed(() => Boolean(cycle.value?.is_open_for_ordering));
const orderItems = computed(() => order.value?.items ?? []);
const totalPositions = computed(() => orderItems.value.reduce((sum, item) => sum + Number(item.quantity), 0));
const activeFridgeItemsCount = computed(() => fridgeItems.value.length);
const weeklyDeadlineLabel = computed(() => {
    if (cycle.value?.closes_at) {
        const closeDate = new Date(cycle.value.closes_at);

        if (!Number.isNaN(closeDate.getTime())) {
            return closeDate.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        }
    }

    if (!cycle.value?.starts_at) {
        return 'Пятница, 12:00';
    }

    const start = new Date(cycle.value.starts_at);
    if (Number.isNaN(start.getTime())) {
        return 'Пятница, 12:00';
    }

    const mondayBasedDay = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - mondayBasedDay + 4);
    start.setHours(12, 0, 0, 0);

    return start.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const filteredItems = computed(() => {
    const q = search.value.trim().toLowerCase();

    return items.value.filter((item) => {
        const categoryOk = selectedCategory.value ? item.category_id === selectedCategory.value : true;
        const searchableText = [
            item.title,
            item.description,
            item.composition,
            item.category?.name,
        ].filter(Boolean).join(' ').toLowerCase();
        const searchOk = q ? searchableText.includes(q) : true;

        return categoryOk && searchOk;
    });
});

const categoryItemCount = (categoryId) => {
    return items.value.filter((item) => item.category_id === categoryId).length;
};

const orderItemByMenuItem = computed(() => {
    const map = new Map();

    for (const item of orderItems.value) {
        map.set(item.menu_item_id, item);
    }

    return map;
});

const menuItemsById = computed(() => {
    const map = new Map();

    for (const item of items.value) {
        map.set(item.id, item);
    }

    return map;
});

const setOrderFromResponse = (payload) => {
    order.value = payload?.data ?? payload ?? null;
};

const toggleFavorite = (menuItemId) => {
    if (!isAuthenticated.value) {
        openAuthModal('Войдите, чтобы добавить блюдо в избранное.');
        return;
    }

    const nextFavorites = new Set(favoriteIds.value);

    if (nextFavorites.has(menuItemId)) {
        nextFavorites.delete(menuItemId);
    } else {
        nextFavorites.add(menuItemId);
    }

    favoriteIds.value = nextFavorites;
};

const addItem = async (menuItemId) => {
    if (!token.value) {
        openAuthModal('Войдите, чтобы добавить блюдо в заказ.');
        return;
    }

    actionLoading.value = true;
    error.value = '';
    info.value = '';

    try {
        const response = await api('/my-order/items', {
            method: 'POST',
            body: {
                menu_item_id: menuItemId,
                quantity: 1,
            },
        });

        setOrderFromResponse(response);
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const changeQuantity = async (orderItem, quantity) => {
    actionLoading.value = true;
    error.value = '';

    try {
        if (quantity <= 0) {
            const response = await api(`/my-order/items/${orderItem.id}`, {
                method: 'DELETE',
            });
            setOrderFromResponse(response);

            return;
        }

        const response = await api(`/my-order/items/${orderItem.id}`, {
            method: 'PATCH',
            body: { quantity },
        });
        setOrderFromResponse(response);
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const submitOrder = async () => {
    actionLoading.value = true;
    error.value = '';
    info.value = '';

    try {
        const response = await api('/my-order/submit', {
            method: 'POST',
        });

        setOrderFromResponse(response);
        info.value = 'Заказ подтвержден.';
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const eatOneFromFridge = async (fridgeItemId) => {
    actionLoading.value = true;
    error.value = '';

    try {
        await api(`/my-fridge/items/${fridgeItemId}/eat-one`, { method: 'PATCH' });
        await loadFridgeData();
        info.value = 'Холодильник обновлен.';
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const eatAllFromFridge = async (fridgeItemId) => {
    actionLoading.value = true;
    error.value = '';

    try {
        await api(`/my-fridge/items/${fridgeItemId}/eat-all`, { method: 'PATCH' });
        await loadFridgeData();
        info.value = 'Позиция отмечена как съеденная.';
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const discardFromFridge = async (fridgeItemId) => {
    actionLoading.value = true;
    error.value = '';

    try {
        await api(`/my-fridge/items/${fridgeItemId}/discard`, { method: 'PATCH' });
        await loadFridgeData();
        info.value = 'Позиция отмечена как выброшенная.';
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

const statusLabel = (status) => {
    const labels = {
        ordered: 'Заказано',
        arrived: 'Пришло',
        received: 'Получено',
        eaten: 'Съедено',
        cancelled: 'Отменено',
    };

    return labels[status] ?? status;
};

const fridgeStatusLabel = (status) => {
    const labels = {
        in_fridge: 'В холодильнике',
        eaten: 'Съедено',
        discarded: 'Выброшено',
        expired: 'Просрочено',
    };

    return labels[status] ?? status;
};

const formatPrice = (value) => {
    const amount = Number(value ?? 0);

    if (Number.isNaN(amount)) {
        return '0 ₽';
    }

    const hasFraction = Math.abs(amount % 1) > 0.001;

    return `${amount.toLocaleString('ru-RU', {
        minimumFractionDigits: hasFraction ? 2 : 0,
        maximumFractionDigits: 2,
    })} ₽`;
};

const withUnit = (value, unit) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const text = String(value).trim();
    if (/[a-zA-Z\u0400-\u04FF]/.test(text)) {
        return text;
    }

    return `${text} ${unit}`;
};

const compactNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return String(value ?? '-');
    }

    return amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 1,
    });
};

const nutritionLine = (item) => {
    const kcal = item.calories ? `${compactNumber(item.calories)} ккал` : '-';
    const proteins = compactNumber(item.proteins);
    const fats = compactNumber(item.fats);
    const carbs = compactNumber(item.carbs);

    return `${kcal} • Б ${proteins} • Ж ${fats} • У ${carbs}`;
};

const extractShelfLife = (item) => {
    const description = item.description ?? '';
    const match = description.match(/срок годности:\s*([^.,;]+)/i);

    return match ? match[1].trim() : null;
};

const shelfLifeLabel = (item) => {
    const shelfLife = extractShelfLife(item);
    const daysMatch = shelfLife?.match(/(\d+)\s*сут/i);

    if (daysMatch?.[1]) {
        return `${Number(daysMatch[1]) * 24} ч`;
    }

    return shelfLife;
};

const categoryIcon = (categoryName) => {
    const normalized = (categoryName ?? '').toLowerCase();

    if (normalized.includes('втор') || normalized.includes('горяч')) {
        return UtensilsCrossed;
    }

    if (normalized.includes('выпеч') || normalized.includes('блин')) {
        return Croissant;
    }

    if (normalized.includes('салат')) {
        return Salad;
    }

    if (normalized.includes('суп')) {
        return Soup;
    }

    return LayoutGrid;
};

const orderItemImage = (orderItem) => menuItemsById.value.get(orderItem.menu_item_id)?.image_url ?? null;
const orderItemWeight = (orderItem) => menuItemsById.value.get(orderItem.menu_item_id)?.weight ?? null;
const orderItemTotal = (orderItem) => formatPrice(Number(orderItem.price_snapshot) * Number(orderItem.quantity));

const orderStatusLabel = (status) => {
    const labels = {
        draft: 'Черновик',
        submitted: 'Подтвержден',
        cancelled: 'Отменен',
    };

    return labels[status] ?? status;
};

const favoritesCount = computed(() => favoriteIds.value.size);
const ordersCount = computed(() => (order.value ? 1 : 0));
const lastOrderLabel = computed(() => {
    if (!order.value?.status) {
        return '';
    }

    return `Текущий заказ: ${orderStatusLabel(order.value.status)}`;
});

const clearOrder = async () => {
    if (!orderItems.value.length) {
        return;
    }

    actionLoading.value = true;
    error.value = '';
    info.value = '';

    try {
        let latestOrder = order.value;

        for (const item of orderItems.value) {
            const response = await api(`/my-order/items/${item.id}`, {
                method: 'DELETE',
            });
            latestOrder = response?.data ?? response ?? latestOrder;
        }

        order.value = latestOrder;
    } catch (e) {
        error.value = e.message;
    } finally {
        actionLoading.value = false;
    }
};

watch(token, (value) => {
    if (value) {
        if (rememberMe.value) {
            localStorage.setItem('lunch_mvp_token', value);
            sessionStorage.removeItem('lunch_mvp_token');
        } else {
            sessionStorage.setItem('lunch_mvp_token', value);
            localStorage.removeItem('lunch_mvp_token');
        }
    } else {
        localStorage.removeItem('lunch_mvp_token');
        sessionStorage.removeItem('lunch_mvp_token');
    }

    document.body.classList.toggle('app-authenticated', Boolean(value));
}, { immediate: true });

watch(rememberMe, (value) => {
    if (!token.value) {
        return;
    }

    if (value) {
        localStorage.setItem('lunch_mvp_token', token.value);
        sessionStorage.removeItem('lunch_mvp_token');
    } else {
        sessionStorage.setItem('lunch_mvp_token', token.value);
        localStorage.removeItem('lunch_mvp_token');
    }
});

onMounted(async () => {
    window.Telegram?.WebApp?.ready();
    window.Telegram?.WebApp?.expand();

    await ensureAuth();
    await loadData();
});
</script>

<template>
    <div class="min-h-screen bg-[#f8faff] text-slate-900">
            <header class="sticky top-0 z-40 border-b border-[#e7ecf6] bg-white/95 shadow-[0_8px_30px_rgba(21,39,75,0.04)] backdrop-blur">
                <div class="header-inner app-header-shell">
                    <div class="flex min-w-0 items-center gap-3">
                        <img
                            :src="'/assets/branding/nethammer-hammer.svg'"
                            alt=""
                            class="h-11 w-[58px] shrink-0 object-contain"
                            aria-hidden="true"
                        />
                        <div class="leading-none">
                            <p class="text-[23px] font-bold tracking-[-0.4px] text-[#111827]">nethammer</p>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.12em] text-[#111827]/70">eda.</p>
                        </div>
                    </div>

                    <div class="app-header-meta hidden min-w-[420px] items-center justify-between rounded-[12px] border border-[#e5ebf7] bg-white px-5 py-2.5 text-sm shadow-[0_10px_28px_rgba(34,58,104,0.05)] lg:flex">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 place-items-center rounded-full border border-[#dfe6f3] text-[#2459d9]">
                                <CalendarRange class="size-4" />
                            </span>
                            <div>
                                <p class="font-bold leading-5 text-[#111827]">{{ cycle ? cycle.title : 'Недельный цикл не создан' }}</p>
                                <p class="text-[12px] leading-4 text-[#66769f]">Дедлайн заказа: {{ weeklyDeadlineLabel }}</p>
                            </div>
                        </div>

                        <Badge
                            :class="isOpenForOrdering ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-red-100 bg-red-50 text-red-600'"
                            class="h-8 rounded-[10px] px-3 text-[12px] font-bold"
                            variant="outline"
                        >
                            <span class="mr-2 h-2 w-2 rounded-full" :class="isOpenForOrdering ? 'bg-emerald-500' : 'bg-red-500'"></span>
                            {{ isOpenForOrdering ? 'Заказ открыт' : 'Заказ закрыт' }}
                        </Badge>
                    </div>

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
                            @click="openProfileModal"
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
                            @click="openAuthModal()"
                        >
                            <div class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-[#f1f5ff] text-[#2459d9]">
                                <User class="size-5" />
                            </div>
                            Войти
                        </button>
                    </div>
                </div>
            </header>

            <Alert
                v-if="error"
                variant="destructive"
                class="page-shell mb-4 mt-4 border-red-200 bg-red-50 text-red-700"
                role="alert"
                aria-live="assertive"
            >
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <Alert
                v-if="info"
                class="page-shell mb-4 mt-4 border-blue-200 bg-blue-50 text-blue-700"
                role="status"
                aria-live="polite"
            >
                <AlertDescription>{{ info }}</AlertDescription>
            </Alert>

            <section
                class="page-shell catalog-layout pb-8 pt-6"
                :class="isAuthenticated ? 'catalog-layout--auth' : 'catalog-layout--guest'"
            >
                <aside class="catalog-sidebar lg:sticky lg:top-24 lg:self-start">
                    <nav
                        class="rounded-[18px] border border-[#e5ebf7] bg-white p-4 shadow-[0_14px_38px_rgba(21,39,75,0.06)]"
                        aria-label="Категории блюд"
                    >
                        <button
                            type="button"
                            class="flex h-[56px] w-full items-center justify-between rounded-[12px] px-4 text-left text-[15px] font-bold transition"
                            :class="selectedCategory === null ? 'bg-[#0f52ff] text-white shadow-[0_12px_24px_rgba(15,82,255,0.24)]' : 'text-[#25314d] hover:bg-[#f4f7ff]'"
                            :aria-pressed="selectedCategory === null"
                            @click="selectedCategory = null"
                        >
                            <span class="flex items-center gap-3">
                                <LayoutGrid class="size-5" />
                                Все категории
                            </span>
                            <Badge
                                variant="outline"
                                class="rounded-[8px] border-0 px-2 text-[12px] font-bold"
                                :class="selectedCategory === null ? 'bg-white/18 text-white' : 'bg-[#f0f4ff] text-[#66769f]'"
                            >
                                {{ items.length }}
                            </Badge>
                        </button>

                        <div class="mt-3 space-y-1">
                            <button
                                v-for="category in categories"
                                :key="category.id"
                                type="button"
                                class="flex h-[56px] w-full items-center justify-between rounded-[12px] px-4 text-left text-[15px] font-semibold transition"
                                :class="selectedCategory === category.id ? 'bg-[#edf3ff] text-[#0f52ff]' : 'text-[#25314d] hover:bg-[#f7f9fe]'"
                                :aria-pressed="selectedCategory === category.id"
                                @click="selectedCategory = category.id"
                            >
                                <span class="flex items-center gap-3">
                                    <component
                                        :is="categoryIcon(category.name)"
                                        class="size-5"
                                        :class="selectedCategory === category.id ? 'text-[#0f52ff]' : 'text-[#60719a]'"
                                    />
                                    {{ category.name }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="rounded-[8px] border-0 bg-[#f0f4ff] px-2 text-[12px] font-bold text-[#66769f]"
                                >
                                    {{ categoryItemCount(category.id) }}
                                </Badge>
                            </button>
                        </div>
                    </nav>
                </aside>

                <Card class="overflow-visible border-0 bg-transparent text-slate-900 shadow-none">
                    <div class="mb-6 flex flex-col gap-5 2xl:flex-row 2xl:items-end 2xl:justify-between">
                        <div>
                            <h1 class="text-[30px] font-bold leading-tight tracking-[-0.4px] text-[#111827] md:text-[32px]">
                                Каталог блюд
                            </h1>
                            <p class="mt-1.5 text-[16px] font-medium text-[#66769f]">
                                {{ filteredItems.length }} блюд на ваш выбор
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-3 sm:flex-row 2xl:max-w-[560px]">
                            <div class="relative min-w-0 flex-1">
                                <Search class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-[#7080a3]" />
                                <Input
                                    id="menu-search"
                                    v-model="search"
                                    type="search"
                                    placeholder="Поиск блюд, ингредиентов..."
                                    class="h-[52px] rounded-[12px] border-[#e1e8f5] bg-white pl-12 pr-4 text-[15px] font-medium text-[#1f2a44] shadow-[0_10px_28px_rgba(21,39,75,0.04)] placeholder:text-[#7080a3] focus-visible:border-[#0f52ff] focus-visible:ring-[#0f52ff]/15"
                                />
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                class="h-[52px] rounded-[12px] border-[#e1e8f5] bg-white px-6 text-[15px] font-bold text-[#25314d] shadow-[0_10px_28px_rgba(21,39,75,0.04)] hover:bg-[#f4f7ff] hover:text-[#0f52ff]"
                            >
                                <SlidersHorizontal class="mr-2 size-5 text-[#0f52ff]" />
                                Фильтры
                            </Button>
                        </div>
                    </div>

                    <CardContent class="space-y-5 bg-transparent p-0">
                        <div v-if="loading" class="dishes-grid">
                            <Card
                                v-for="skeleton in menuSkeletonRows"
                                :key="`menu-skeleton-${skeleton}`"
                                class="border-slate-200 bg-white/90"
                            >
                                <CardContent class="space-y-3 p-3">
                                    <Skeleton class="aspect-square w-full rounded-xl bg-slate-200/80" />
                                    <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                                    <Skeleton class="h-4 w-full rounded-md bg-slate-200/70" />
                                    <Skeleton class="h-4 w-2/3 rounded-md bg-slate-200/70" />
                                    <div class="flex items-center justify-between">
                                        <Skeleton class="h-5 w-16 rounded-md bg-slate-200/80" />
                                        <Skeleton class="h-9 w-28 rounded-lg bg-slate-200/80" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card
                            v-else-if="filteredItems.length === 0"
                            class="border-slate-200 bg-slate-50"
                        >
                            <CardContent class="py-14 text-center text-slate-600">
                                <p class="text-base font-semibold text-slate-800">Ничего не найдено</p>
                                <p class="mt-2 text-sm text-slate-500">Попробуйте изменить поисковый запрос или выбрать другую категорию.</p>
                            </CardContent>
                        </Card>

                        <div v-else class="dishes-grid">
                            <Card
                                v-for="item in filteredItems"
                                :key="item.id"
                                class="menu-card overflow-hidden rounded-[12px] border border-[#e5ebf7] bg-white text-slate-900 shadow-[0_10px_28px_rgba(21,39,75,0.04)] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#cfdaee] hover:shadow-[0_18px_38px_rgba(21,39,75,0.10)]"
                            >
                                <CardContent class="flex h-full flex-col gap-0 p-0">
                                    <div class="relative aspect-[4/3] overflow-hidden bg-[#eef3fb]">
                                        <img
                                            v-if="item.image_url"
                                            :src="item.image_url"
                                            :alt="item.title"
                                            class="size-full object-cover"
                                            loading="eager"
                                            decoding="async"
                                        />
                                        <div v-else class="flex size-full items-center justify-center px-4 text-center text-xs font-medium text-[#7080a3]">
                                            Фото скоро загрузим
                                        </div>

                                        <button
                                            type="button"
                                            class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-[9px] border border-[#e1e8f5] bg-white/95 text-[#7080a3] shadow-[0_8px_18px_rgba(21,39,75,0.10)] transition hover:text-rose-500"
                                            :class="favoriteIds.has(item.id) ? 'border-rose-100 bg-rose-50 text-rose-500' : ''"
                                            :aria-label="favoriteIds.has(item.id) ? `Убрать из избранного: ${item.title}` : `Добавить в избранное: ${item.title}`"
                                            :aria-pressed="favoriteIds.has(item.id)"
                                            @click="toggleFavorite(item.id)"
                                        >
                                            <Heart class="size-4" :class="favoriteIds.has(item.id) ? 'fill-current' : ''" />
                                        </button>

                                        <div
                                            v-if="shelfLifeLabel(item)"
                                            class="absolute bottom-3 left-3 inline-flex h-7 items-center gap-1 rounded-[8px] bg-white/95 px-2.5 text-[12px] font-bold text-[#25314d] shadow-[0_8px_18px_rgba(21,39,75,0.10)]"
                                        >
                                            <Clock3 class="size-3.5 text-[#0f52ff]" />
                                            {{ shelfLifeLabel(item) }}
                                        </div>
                                    </div>

                                    <div class="flex flex-1 flex-col p-4">
                                        <h3 class="line-clamp-2 min-h-[46px] text-[16px] font-bold leading-[1.42] text-[#172033]">{{ item.title }}</h3>
                                        <p class="mt-2 text-[13px] font-semibold leading-5 text-[#6b7aa0]">{{ item.weight }}</p>
                                        <p class="mt-2 min-h-9 text-[12px] font-medium leading-[18px] text-[#65769b]">
                                            {{ nutritionLine(item) }}
                                        </p>

                                        <div class="mt-auto flex min-h-12 items-center justify-between gap-3 pt-5">
                                            <p class="text-[21px] font-black leading-none text-[#111827]">{{ formatPrice(item.price) }}</p>
                                            <Button
                                                v-if="!orderItemByMenuItem.get(item.id)"
                                                type="button"
                                                size="sm"
                                                :disabled="isAuthenticated && (!isOpenForOrdering || actionLoading)"
                                                class="h-10 rounded-[8px] border-0 bg-[#0f52ff] px-4 text-[13px] font-bold text-white shadow-[0_10px_20px_rgba(15,82,255,0.22)] hover:bg-[#0648ec]"
                                                @click="addItem(item.id)"
                                            >
                                                <Plus class="mr-1 size-4" />
                                                Добавить
                                            </Button>
                                            <div
                                                v-else
                                                class="inline-flex h-10 items-center gap-1 rounded-[8px] border border-[#e1e8f5] bg-white px-1.5 shadow-[0_8px_16px_rgba(21,39,75,0.05)]"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="h-8 w-8 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                                    :disabled="!isOpenForOrdering || actionLoading"
                                                    :aria-label="`Уменьшить количество: ${item.title}`"
                                                    @click="changeQuantity(orderItemByMenuItem.get(item.id), orderItemByMenuItem.get(item.id).quantity - 1)"
                                                >
                                                    <Minus class="size-4" />
                                                </Button>
                                                <span class="min-w-7 text-center text-[15px] font-bold text-[#111827]">{{ orderItemByMenuItem.get(item.id).quantity }}</span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="h-8 w-8 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                                    :disabled="!isOpenForOrdering || actionLoading"
                                                    :aria-label="`Увеличить количество: ${item.title}`"
                                                    @click="changeQuantity(orderItemByMenuItem.get(item.id), orderItemByMenuItem.get(item.id).quantity + 1)"
                                                >
                                                    <Plus class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="isAuthenticated" class="catalog-order-panel border border-[#e5ebf7] bg-white text-slate-900 shadow-[0_14px_38px_rgba(21,39,75,0.06)] lg:col-start-2 xl:sticky xl:top-24 xl:col-start-auto xl:max-h-[calc(100vh-120px)] xl:overflow-hidden">
                    <CardContent class="flex h-full min-h-0 flex-col p-0">
                        <Tabs v-model="activeSidebarTab" class="flex h-full min-h-0 w-full flex-col">
                            <TabsList class="grid h-[58px] w-full shrink-0 grid-cols-2 rounded-none border-0 border-b border-[#e5ebf7] bg-white p-0">
                                <TabsTrigger
                                    value="order"
                                    class="relative gap-2 rounded-none border-0 bg-transparent text-sm font-bold text-[#7080a3] shadow-none data-active:bg-transparent data-active:text-[#0f52ff] data-active:shadow-none after:absolute after:bottom-[-1px] after:left-0 after:h-0.5 after:w-full after:rounded-full after:bg-transparent data-active:after:bg-[#0f52ff]"
                                >
                                    Ваш заказ
                                    <Badge class="grid h-5 min-w-5 place-items-center rounded-full bg-[#0f52ff] px-1.5 text-[11px] font-bold text-white">
                                        {{ totalPositions }}
                                    </Badge>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="fridge"
                                    class="relative gap-2 rounded-none border-0 bg-transparent text-sm font-bold text-[#7080a3] shadow-none data-active:bg-transparent data-active:text-[#0f52ff] data-active:shadow-none after:absolute after:bottom-[-1px] after:left-0 after:h-0.5 after:w-full after:rounded-full after:bg-transparent data-active:after:bg-[#0f52ff]"
                                >
                                    Холодильник
                                    <Badge class="grid h-5 min-w-5 place-items-center rounded-full bg-[#c7cfdf] px-1.5 text-[11px] font-bold text-white">
                                        {{ activeFridgeItemsCount }}
                                    </Badge>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="order" class="mt-0 flex min-h-0 flex-1 flex-col px-5 pb-5 pt-5">
                                <div class="flex shrink-0 items-center justify-between gap-2">
                                    <p class="text-[16px] font-black text-[#111827]">{{ totalPositions }} товаров</p>
                                    <button
                                        v-if="orderItems.length"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-[8px] px-2 py-1 text-[12px] font-bold text-[#ff3347] transition hover:bg-rose-50"
                                        :disabled="actionLoading"
                                        @click="clearOrder"
                                    >
                                        Очистить
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <Alert
                                    v-if="!isOpenForOrdering"
                                    class="mt-4 shrink-0 rounded-[10px] border-[#f2dbc4] bg-[#fff7ef] text-[#b06b2b]"
                                >
                                    <AlertDescription>
                                        Редактирование заказа закрыто
                                        <span class="block text-xs text-[#c17e3d]">Прием заказов завершен {{ weeklyDeadlineLabel }}</span>
                                    </AlertDescription>
                                </Alert>

                                <div v-if="loading" class="mt-4 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                                    <div
                                        v-for="skeleton in orderSkeletonRows"
                                        :key="`order-skeleton-${skeleton}`"
                                        class="space-y-2 rounded-[10px] border border-[#e5ebf7] bg-white p-3"
                                    >
                                        <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                                        <Skeleton class="h-4 w-1/2 rounded-md bg-slate-200/75" />
                                        <Skeleton class="h-8 w-24 rounded-md bg-slate-200/80" />
                                    </div>
                                </div>

                                <div
                                    v-else-if="!order || orderItems.length === 0"
                                    class="mt-4 rounded-[12px] border border-[#e5ebf7] bg-[#f8faff] px-5 py-10 text-center"
                                >
                                    <ShoppingCart class="mx-auto size-7 text-[#a3aec6]" />
                                    <p class="mt-3 text-base font-bold text-[#172033]">Заказ пока пустой</p>
                                    <p class="mt-1 text-sm font-medium text-[#7080a3]">Добавьте блюда из каталога.</p>
                                </div>

                                <div v-else class="mt-4 min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                                    <article
                                        v-for="item in orderItems"
                                        :key="item.id"
                                        class="group relative grid grid-cols-[64px_minmax(0,1fr)] gap-3 border-b border-[#eef2f8] pb-4 last:border-b-0"
                                    >
                                        <img
                                            v-if="orderItemImage(item)"
                                            :src="orderItemImage(item)"
                                            :alt="item.title_snapshot"
                                            class="h-16 w-16 rounded-[10px] border border-[#e5ebf7] object-cover"
                                            loading="eager"
                                            decoding="async"
                                        />
                                        <div v-else class="grid h-16 w-16 place-items-center rounded-[10px] border border-[#e5ebf7] bg-[#f0f4ff] text-[#7080a3]">
                                            <UtensilsCrossed class="size-5" />
                                        </div>

                                        <div class="min-w-0 pr-6">
                                            <button
                                                v-if="isOpenForOrdering"
                                                type="button"
                                                class="absolute right-0 top-0 grid h-7 w-7 place-items-center rounded-[7px] text-[#66769f] transition hover:bg-rose-50 hover:text-[#ff3347]"
                                                :aria-label="`Удалить блюдо: ${item.title_snapshot}`"
                                                @click="changeQuantity(item, 0)"
                                            >
                                                <X class="size-4" />
                                            </button>

                                            <p class="line-clamp-2 text-[14px] font-bold leading-5 text-[#172033]">{{ item.title_snapshot }}</p>
                                            <p class="mt-1 text-[12px] font-semibold text-[#7080a3]">
                                                {{ orderItemWeight(item) || 'Порция' }}
                                            </p>

                                            <div class="mt-2 flex items-center justify-between gap-3">
                                                <p class="text-[16px] font-black text-[#111827]">
                                                    {{ orderItemTotal(item) }}
                                                </p>

                                                <div class="inline-flex h-9 items-center gap-1 rounded-[9px] border border-[#e1e8f5] bg-white px-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        class="h-7 w-7 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                                        :disabled="!isOpenForOrdering"
                                                        :aria-label="`Уменьшить количество: ${item.title_snapshot}`"
                                                        @click="changeQuantity(item, item.quantity - 1)"
                                                    >
                                                        <Minus class="size-4" />
                                                    </Button>
                                                    <span class="min-w-6 text-center text-[14px] font-bold text-[#111827]">{{ item.quantity }}</span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        class="h-7 w-7 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                                        :disabled="!isOpenForOrdering"
                                                        :aria-label="`Увеличить количество: ${item.title_snapshot}`"
                                                        @click="changeQuantity(item, item.quantity + 1)"
                                                    >
                                                        <Plus class="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <div class="mt-5 shrink-0 border-t border-[#e5ebf7] pt-5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[16px] font-bold text-[#172033]">Итого</p>
                                        <strong class="text-[24px] font-black leading-none text-[#111827]">{{ formatPrice(order?.total_price ?? 0) }}</strong>
                                    </div>
                                    <p class="mt-2 text-[12px] font-semibold text-[#7080a3]">Статус: {{ orderStatusLabel(order?.status ?? 'draft') }}</p>
                                </div>

                                <Button
                                    type="button"
                                    class="h-[52px] w-full rounded-[10px] bg-[#0f52ff] text-[15px] font-bold text-white shadow-[0_14px_26px_rgba(15,82,255,0.22)] hover:bg-[#0648ec] disabled:bg-[#d8e0ee] disabled:text-[#7383a6] disabled:shadow-none"
                                    :disabled="!isOpenForOrdering || !orderItems.length || actionLoading"
                                    @click="submitOrder"
                                >
                                    <Loader2 v-if="actionLoading" class="mr-2 size-4 animate-spin" />
                                    {{ orderItems.length ? 'Оформить заказ' : 'Добавьте блюда' }}
                                </Button>
                            </TabsContent>

                            <TabsContent value="fridge" class="mt-0 min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-5 pt-5">
                                <div class="flex items-center justify-between gap-2">
                                    <h2 class="text-[15px] font-black text-[#111827]">Мой холодильник</h2>
                                    <Badge variant="outline" class="rounded-[8px] border-[#dce5f6] bg-[#f2f6ff] text-xs font-bold text-[#0f52ff]">
                                        {{ activeFridgeItemsCount }} шт.
                                    </Badge>
                                </div>

                                <div v-if="fridgeLoading" class="space-y-2">
                                    <div
                                        v-for="skeleton in orderSkeletonRows"
                                        :key="`fridge-skeleton-${skeleton}`"
                                        class="space-y-2 rounded-[10px] border border-[#e5ebf7] bg-white p-3"
                                    >
                                        <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                                        <Skeleton class="h-4 w-1/2 rounded-md bg-slate-200/75" />
                                        <Skeleton class="h-8 w-full rounded-md bg-slate-200/80" />
                                    </div>
                                </div>

                                <div
                                    v-else-if="fridgeItems.length === 0"
                                    class="rounded-[12px] border border-[#e5ebf7] bg-[#f8faff] px-5 py-10 text-center"
                                >
                                    <Refrigerator class="mx-auto size-7 text-[#a3aec6]" />
                                    <p class="mt-3 text-base font-bold text-[#172033]">Холодильник пока пуст</p>
                                    <p class="mt-1 text-sm font-medium text-[#7080a3]">После доставки позиции появятся здесь автоматически.</p>
                                </div>

                                <div v-else class="space-y-2">
                                    <article
                                        v-for="item in fridgeItems"
                                        :key="item.id"
                                        class="rounded-[10px] border border-[#e5ebf7] bg-white p-3"
                                    >
                                        <p class="text-sm font-bold leading-snug text-[#172033]">{{ item.title_snapshot }}</p>
                                        <p class="mt-1 text-xs font-semibold text-[#7080a3]">
                                            {{ fridgeStatusLabel(item.status) }} · остаток {{ item.quantity_remaining }}/{{ item.quantity_total }}
                                        </p>

                                        <div class="mt-3 grid grid-cols-3 gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 rounded-[8px] border-[#dce5f6] bg-white text-xs font-bold text-[#25314d] hover:bg-[#f4f7ff]"
                                                :disabled="actionLoading"
                                                @click="eatOneFromFridge(item.id)"
                                            >
                                                Съел 1
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 rounded-[8px] border-[#dce5f6] bg-white text-xs font-bold text-[#25314d] hover:bg-[#f4f7ff]"
                                                :disabled="actionLoading"
                                                @click="eatAllFromFridge(item.id)"
                                            >
                                                Всё
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 rounded-[8px] border-rose-100 bg-rose-50 text-xs font-bold text-rose-600 hover:bg-rose-100"
                                                :disabled="actionLoading"
                                                @click="discardFromFridge(item.id)"
                                            >
                                                Выбросил
                                            </Button>
                                        </div>
                                    </article>
                                </div>

                                <Separator class="bg-[#e5ebf7]" />

                                <div class="space-y-2">
                                    <p class="text-sm font-bold text-[#172033]">История</p>

                                    <div v-if="fridgeHistory.length === 0" class="rounded-[10px] border border-[#e5ebf7] bg-[#f8faff] py-6 text-center text-sm font-medium text-[#7080a3]">
                                            История пока пустая
                                    </div>

                                    <ul v-else class="space-y-2 text-sm text-[#25314d]">
                                        <li
                                            v-for="item in fridgeHistory"
                                            :key="item.id"
                                            class="flex items-center justify-between gap-3 rounded-[8px] border border-[#e5ebf7] bg-[#f8faff] px-3 py-2"
                                        >
                                            <span class="line-clamp-2 font-semibold">{{ item.title_snapshot }}</span>
                                            <Badge variant="outline" class="rounded-[7px] border-[#dce5f6] bg-white text-[#7080a3]">
                                                {{ fridgeStatusLabel(item.status) }}
                                            </Badge>
                                        </li>
                                    </ul>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </CardContent>

                </Card>
            </section>

            <AuthModal
                :open="isAuthModalOpen"
                :email="email"
                :password="password"
                :remember-me="rememberMe"
                :show-password="showPassword"
                :loading="authLoading"
                :error="authError"
                :message="authModalMessage"
                :show-telegram="hasTelegramInitData()"
                @close="closeAuthModal"
                @submit="loginFromWeb"
                @telegram-login="loginFromTelegram"
                @update:email="email = $event"
                @update:password="password = $event"
                @update:remember-me="rememberMe = $event"
                @update:show-password="showPassword = $event"
            />

            <UserProfileModal
                :open="isProfileModalOpen"
                :user="me"
                :favorites-count="favoritesCount"
                :orders-count="ordersCount"
                :last-order-label="lastOrderLabel"
                :notice="profileNotice"
                @close="closeProfileModal"
                @logout="logout"
                @show-favorites="showProfileNotice('Раздел избранного подготовлен. Полноценная страница появится после API избранного.')"
                @show-orders="showProfileNotice('Мои заказы будут связаны с историей заказов после появления отдельного API.')"
                @show-settings="showProfileNotice('Настройки профиля пока не подключены.')"
            />
    </div>
</template>
