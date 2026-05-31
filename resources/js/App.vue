<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { storeToRefs } from 'pinia';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent } from '@/components/ui/card';
import AppHeader from '@/components/AppHeader.vue';
import FridgePanel from '@/components/FridgePanel.vue';
import HistoryPanel from '@/components/HistoryPanel.vue';
import LoginModal from '@/components/LoginModal.vue';
import MenuGrid from '@/components/MenuGrid.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import MobilePanelSheet from '@/components/MobilePanelSheet.vue';
import OrderPanel from '@/components/OrderPanel.vue';
import RequiredFullNameModal from '@/components/RequiredFullNameModal.vue';
import UserProfileModal from '@/components/UserProfileModal.vue';
import { createTelegramLinkToken, fetchTelegramLinkStatus } from '@/api/auth';
import { withTimeout } from '@/lib/async';
import { useAuthStore } from '@/stores/auth';
import { useCatalogStore } from '@/stores/catalog';
import { useFridgeStore } from '@/stores/fridge';
import { useOrderStore } from '@/stores/order';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const catalog = useCatalogStore();
const orderStore = useOrderStore();
const fridge = useFridgeStore();
const ui = useUiStore();
const isDesktopLayout = useMediaQuery('(min-width: 1280px)');
const reopenedForEditing = ref(false);
const deadlinePassedLocally = ref(false);
let deadlineRefreshTimerId = null;

const {
    requireFullName,
    me,
    email,
    password,
    rememberMe,
    showPassword,
    authLoading,
    authError,
    profileSaving,
    profileError,
    isAuthenticated,
    displayUserName,
} = storeToRefs(auth);

const {
    cycle,
    categories,
    items,
    search,
    selectedCategory,
    filteredItems,
    isOpenForOrdering,
    availabilityLabel,
    availabilityDescription,
    weeklyDeadlineLabel,
} = storeToRefs(catalog);

const {
    order,
    orderNotice,
    orderHistory,
    orderHistoryLoading,
    orderHistoryError,
    orderItems,
    totalPositions,
    orderItemByMenuItem,
} = storeToRefs(orderStore);

const {
    fridgeItems,
    fridgeHistory,
    fridgeMeta,
    fridgeLoading,
    activeFridgeItemsCount,
} = storeToRefs(fridge);

const {
    loading,
    actionLoading,
    error,
    info,
    activeSidebarTab,
    isAuthModalOpen,
    authModalMessage,
    isProfileModalOpen,
    favoriteIds,
    favoritesCount,
    favoritesOnly,
    mobilePanel,
} = storeToRefs(ui);

if (activeSidebarTab.value !== 'catalog') {
    ui.activeSidebarTab = 'catalog';
}

const menuSkeletonRows = Array.from({ length: 6 }, (_, index) => index + 1);
const orderSkeletonRows = Array.from({ length: 3 }, (_, index) => index + 1);
const telegramLinkStatus = ref({
    linked: false,
    bot_link: null,
    bot_username: null,
    link_available: false,
});
const telegramLinkLoading = ref(false);
const telegramLinkError = ref('');
const requiredFullNameDraft = ref('');
const requiredFullNameSaving = ref(false);
const requiredFullNameError = ref('');
const telegramLinkStatusTimeoutMs = 4000;
const closedOrderingMessage = 'Приём заказов закрыт.';
const closedOrderingCartClearedMessage = 'Приём заказов закрыт.';
const closedOrderingInfoMessage = 'Приём заказов закрыт.';
const closedOrderingStatusText = 'Приём закрыт';
const cartStatusLabels = {
    open: 'Приём открыт',
    upcoming: 'Приём скоро',
    closed: 'Приём закрыт',
    draft: 'Приём закрыт',
    delivered: 'Приём закрыт',
    archived: 'Приём закрыт',
};
const repeatWhenClosedMessage = 'Повторить заказ можно, когда открыт приём заказов.';
const repeatReplaceConfirmMessage = 'Заменить текущую корзину этим заказом?';

const menuItemsById = computed(() => new Map(items.value.map((item) => [item.id, item])));
const isSubmittedOrder = computed(() => order.value?.status === 'submitted');
const isOrderingWindowOpen = computed(() => isOpenForOrdering.value && !deadlinePassedLocally.value);
const canReopenSubmittedOrder = computed(() => Boolean(order.value?.can_reopen_for_editing) && isOrderingWindowOpen.value);
const canEditOrder = computed(() => isOrderingWindowOpen.value && !isSubmittedOrder.value);
const effectiveAvailabilityLabel = computed(() => {
    if (isOrderingWindowOpen.value) {
        return availabilityLabel.value;
    }

    if (deadlinePassedLocally.value && cycle.value?.status === 'open') {
        return 'Приём заказов закрыт';
    }

    return availabilityLabel.value;
});
const effectiveAvailabilityDescription = computed(() => {
    if (isOrderingWindowOpen.value) {
        return availabilityDescription.value;
    }

    if (deadlinePassedLocally.value && cycle.value?.status === 'open') {
        return '';
    }

    return availabilityDescription.value;
});

const normalizeClosedCycleCopy = (message, options = {}) => {
    const forCart = options.forCart === true;
    const fallback = forCart
        ? closedOrderingCartClearedMessage
        : closedOrderingInfoMessage;

    if (typeof message !== 'string') {
        return fallback;
    }

    const normalized = message.toLowerCase();

    if (
        normalized.includes('черновик')
        || normalized.includes('цикл закрыт')
        || normalized.includes('администратор')
        || normalized.includes('новый заказ можно будет оформить')
        || normalized.includes('приём заказов закрыт')
        || normalized.includes('прием заказов закрыт')
    ) {
        return fallback;
    }

    return message;
};

const orderReadOnlyReason = computed(() => {
    if (isSubmittedOrder.value) {
        if (canReopenSubmittedOrder.value) {
            return 'Заказ отправлен. Его можно изменить до дедлайна.';
        }

        if (deadlinePassedLocally.value || cycle.value?.deadline_passed) {
            return 'Заказ отправлен. Дедлайн прошел, изменения недоступны.';
        }

        return 'Заказ отправлен. Изменения больше недоступны.';
    }

    if (cycleEffectiveState.value === 'upcoming') {
        return 'Приём скоро откроется.';
    }

    return closedOrderingStatusText;
});

const deadlineShortLabel = computed(() => {
    if (cycle.value?.deadline_display) {
        return cycle.value.deadline_display;
    }

    if (cycle.value?.deadline_date && cycle.value?.deadline_time) {
        return `${cycle.value.deadline_date}, ${cycle.value.deadline_time}`;
    }

    return weeklyDeadlineLabel.value;
});

const compactDateTimeLabel = (value) => (typeof value === 'string' ? value.replace(',', '') : '');
const readableDateTimeLabel = (value) => {
    const compact = compactDateTimeLabel(value);
    const match = compact.match(/^(.+)\s+(\d{2}:\d{2})$/);

    return match ? `${match[1]} в ${match[2]}` : compact;
};
const compactDateLabel = (value) => compactDateTimeLabel(value).replace(/\s+\d{2}:\d{2}$/, '');

const cycleEffectiveState = computed(() => {
    if (cycle.value?.effective_state) {
        return cycle.value.effective_state;
    }

    if (cycle.value?.status === 'open') {
        return isOrderingWindowOpen.value ? 'open' : 'closed';
    }

    if (cycle.value?.status === 'sent_to_supplier') {
        return 'closed';
    }

    return cycle.value?.status ?? 'closed';
});

const cartStatusBadgeText = computed(() => {
    if (loading.value) {
        return '';
    }

    return cartStatusLabels[cycleEffectiveState.value] ?? 'Закрыт';
});

const cartStatusDetailText = computed(() => {
    if (loading.value) {
        return '';
    }

    if (cycleEffectiveState.value === 'open' && deadlineShortLabel.value) {
        return `До ${readableDateTimeLabel(deadlineShortLabel.value)}`;
    }

    if (cycleEffectiveState.value === 'upcoming') {
        const opensAt = cycle.value?.opens_at_display || cycle.value?.opens_at_display_full;

        return opensAt ? `Откроется ${readableDateTimeLabel(opensAt)}` : '';
    }

    return '';
});

const cartOpensAtLabel = computed(() => {
    const opensAt = cycle.value?.opens_at_display || cycle.value?.opens_at_display_full;

    return opensAt ? readableDateTimeLabel(opensAt) : '';
});

const cartOpensDateLabel = computed(() => {
    const opensAt = cycle.value?.opens_at_display || cycle.value?.opens_at_display_full;

    return opensAt ? compactDateLabel(opensAt) : '';
});

const disabledCheckoutLabel = computed(() => {
    if (isOrderingWindowOpen.value) {
        return '';
    }

    if (cycleEffectiveState.value === 'upcoming') {
        return cartOpensDateLabel.value ? `Заказы откроются ${cartOpensDateLabel.value}` : 'Заказы скоро откроются';
    }

    if (cycleEffectiveState.value === 'closed' || cycleEffectiveState.value === 'draft') {
        return 'Приём заказов закрыт';
    }

    return '';
});

const disabledCheckoutHelper = computed(() => {
    if (cycleEffectiveState.value === 'upcoming') {
        return cartOpensAtLabel.value ? `Оформить заказ можно с ${cartOpensAtLabel.value}.` : '';
    }

    if (cycleEffectiveState.value === 'closed' || cycleEffectiveState.value === 'draft') {
        return 'Новый цикл появится позже.';
    }

    return '';
});

const emptyCartDetail = computed(() => (
    cycleEffectiveState.value === 'upcoming' ? disabledCheckoutHelper.value : ''
));

const mobileOrderStatusText = computed(() => {
    if (loading.value) {
        return 'Загрузка';
    }

    return cartStatusBadgeText.value;
});

const infoNeedsAttention = (message) => {
    if (typeof message !== 'string' || message.trim() === '') {
        return false;
    }

    const normalized = message.toLowerCase();

    return normalized.includes('недоступ')
        || normalized.includes('не удалось')
        || normalized.includes('ошибка')
        || normalized.includes('закрыт')
        || normalized.includes('некоторые')
        || normalized.includes('подтвердите')
        || normalized.includes('обратитесь');
};

const visibleInfo = computed(() => {
    if (!infoNeedsAttention(info.value)) {
        return '';
    }

    return normalizeClosedCycleCopy(info.value) === closedOrderingInfoMessage ? '' : info.value;
});

const orderPanelDescription = computed(() => (
    cartStatusDetailText.value
        ? `${mobileOrderStatusText.value} · ${cartStatusDetailText.value}`
        : mobileOrderStatusText.value
));

const normalizeFullName = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim().replace(/\s+/g, ' ');
};

const requiredFullNameModalOpen = computed(() => {
    if (!isAuthenticated.value) {
        return false;
    }

    if (!requireFullName.value) {
        return false;
    }

    return normalizeFullName(me.value?.full_name ?? '') === '';
});

const validateRequiredFullName = (value) => {
    const normalized = normalizeFullName(value);

    if (normalized === '') {
        return 'Введите ФИО.';
    }

    if (normalized.length < 5) {
        return 'Минимум 5 символов.';
    }

    if (normalized.length > 120) {
        return 'Максимум 120 символов.';
    }

    if (normalized.split(' ').filter(Boolean).length < 2) {
        return 'Укажите минимум имя и фамилию.';
    }

    return '';
};

const parseCycleTimestamp = (value) => {
    if (!value) {
        return null;
    }

    const timestamp = Date.parse(value);

    return Number.isNaN(timestamp) ? null : timestamp;
};

const parseDeadlineTimestamp = () => parseCycleTimestamp(cycle.value?.closes_at);

const parseStartsAtTimestamp = () => parseCycleTimestamp(cycle.value?.starts_at);

const isClosedOrderingErrorMessage = (message) => {
    if (typeof message !== 'string') {
        return false;
    }

    return message.includes(closedOrderingMessage);
};

const syncClosedOrderingState = async (message = closedOrderingCartClearedMessage) => {
    orderStore.resetOrder();
    reopenedForEditing.value = false;
    ui.error = '';
    ui.info = message;

    try {
        await catalog.loadCatalogData();

        if (auth.token) {
            await orderStore.loadCurrentOrder(auth.token);
            await orderStore.loadOrderHistory(auth.token);
        }
    } catch (e) {
        ui.error = e.message;
    }
};

const refreshOrderingState = async () => {
    try {
        await catalog.loadCatalogData();

        if (auth.token) {
            await orderStore.loadCurrentOrder(auth.token);
            await orderStore.loadOrderHistory(auth.token);
            if (orderNotice.value) {
                const normalizedNotice = normalizeClosedCycleCopy(orderNotice.value);
                ui.info = normalizedNotice === closedOrderingInfoMessage ? '' : normalizedNotice;
            }
        }
    } catch (e) {
        ui.error = e.message;
    }
};

const clearDeadlineRefreshTimer = () => {
    if (deadlineRefreshTimerId === null) {
        return;
    }

    clearTimeout(deadlineRefreshTimerId);
    deadlineRefreshTimerId = null;
};

const scheduleDeadlineRefresh = () => {
    clearDeadlineRefreshTimer();
    deadlinePassedLocally.value = Boolean(cycle.value?.deadline_passed);

    const refreshTimestamp = cycleEffectiveState.value === 'upcoming'
        ? parseStartsAtTimestamp()
        : (isOpenForOrdering.value ? parseDeadlineTimestamp() : null);

    if (refreshTimestamp === null) {
        return;
    }

    const delayMs = refreshTimestamp - Date.now();

    if (delayMs <= 0) {
        void refreshOrderingState();
        return;
    }

    deadlineRefreshTimerId = setTimeout(() => {
        if (cycleEffectiveState.value !== 'upcoming') {
            deadlinePassedLocally.value = true;
        }

        void refreshOrderingState();
    }, delayMs + 50);
};

const displayedItems = computed(() => {
    if (!favoritesOnly.value) {
        return filteredItems.value;
    }

    return filteredItems.value.filter((item) => favoriteIds.value.has(item.id));
});

const hasActiveFilters = computed(() => {
    return Boolean(search.value.trim() || selectedCategory.value !== null || favoritesOnly.value);
});

const isCatalogView = computed(() => {
    if (!isAuthenticated.value) {
        return true;
    }

    if (!isDesktopLayout.value) {
        return true;
    }

    return activeSidebarTab.value === 'catalog';
});

const isOrderView = computed(() => (
    isAuthenticated.value
    && isDesktopLayout.value
    && activeSidebarTab.value === 'order'
));

const isFridgeView = computed(() => (
    isAuthenticated.value
    && isDesktopLayout.value
    && activeSidebarTab.value === 'fridge'
));

const isHistoryView = computed(() => (
    isAuthenticated.value
    && isDesktopLayout.value
    && activeSidebarTab.value === 'history'
));

watch(isDesktopLayout, (desktop) => {
    if (desktop) {
        ui.closeMobilePanel();
    }
});

watch(() => order.value?.status, (status) => {
    if (status !== 'draft') {
        reopenedForEditing.value = false;
    }
});

watch(
    [
        () => cycle.value?.id,
        () => cycle.value?.starts_at,
        () => cycle.value?.closes_at,
        () => cycle.value?.effective_state,
        isOpenForOrdering,
    ],
    () => {
        scheduleDeadlineRefresh();
    },
    { immediate: true },
);

watch(requiredFullNameModalOpen, (open) => {
    if (!open) {
        return;
    }

    requiredFullNameDraft.value = normalizeFullName(me.value?.full_name ?? '');
    requiredFullNameError.value = '';
});

watch(isProfileModalOpen, async (open) => {
    if (!open || !auth.token) {
        return;
    }

    try {
        await orderStore.loadOrderHistory(auth.token);
    } catch {
        // History errors are shown inside profile tab.
    }
});

const resetProtectedState = () => {
    orderStore.resetOrder();
    fridge.resetFridge();
    ui.resetSessionUi();
    reopenedForEditing.value = false;
};

const openAuthModal = (message = '') => {
    auth.authError = '';
    ui.openAuthModal(message);
};

const closeAuthModal = () => {
    auth.authError = '';
    ui.closeAuthModal();
};

const resetTelegramLinkStatus = () => {
    telegramLinkStatus.value = {
        linked: false,
        bot_link: null,
        bot_username: null,
        link_available: false,
    };
    telegramLinkError.value = '';
};

const loadTelegramLinkStatus = async () => {
    if (!auth.token) {
        resetTelegramLinkStatus();
        return;
    }

    try {
        const response = await withTimeout(
            fetchTelegramLinkStatus(auth.token),
            telegramLinkStatusTimeoutMs,
            'telegram_link_status_timeout',
        );
        telegramLinkStatus.value = {
            linked: Boolean(response.data?.linked),
            bot_link: response.data?.bot_link ?? null,
            bot_username: response.data?.bot_username ?? null,
            link_available: Boolean(response.data?.link_available),
        };
    } catch {
        resetTelegramLinkStatus();
    }
};

const openTelegramBotLink = () => {
    const botLink = telegramLinkStatus.value.bot_link;

    if (!botLink) {
        telegramLinkError.value = 'Привязка временно недоступна.';
        return;
    }

    telegramLinkError.value = '';
    window.open(botLink, '_blank', 'noopener,noreferrer');
};

const linkTelegramFromProfile = async () => {
    if (!auth.token) {
        openAuthModal('Войдите, чтобы привязать Telegram.');
        return;
    }

    telegramLinkLoading.value = true;
    telegramLinkError.value = '';
    ui.error = '';
    ui.info = '';

    try {
        const response = await createTelegramLinkToken(auth.token);
        const deepLink = response.data?.deep_link ?? '';

        if (!deepLink) {
            throw new Error('empty_deep_link');
        }

        window.open(deepLink, '_blank', 'noopener,noreferrer');
        ui.info = 'Откройте Telegram и подтвердите привязку в боте.';
        await loadTelegramLinkStatus();
    } catch {
        telegramLinkError.value = 'Не удалось создать ссылку. Попробуйте ещё раз.';
    } finally {
        telegramLinkLoading.value = false;
    }
};

const loadData = async () => {
    ui.loading = true;
    ui.error = '';

    try {
        await catalog.loadCatalogData();

        if (auth.token) {
            await Promise.all([
                orderStore.loadCurrentOrder(auth.token),
                orderStore.loadOrderHistory(auth.token),
                fridge.loadFridgeData(auth.token),
            ]);
            if (orderNotice.value) {
                const normalizedNotice = normalizeClosedCycleCopy(orderNotice.value);
                ui.info = normalizedNotice === closedOrderingInfoMessage ? '' : normalizedNotice;
            }
            void loadTelegramLinkStatus();
        } else {
            orderStore.resetOrder();
            fridge.resetFridge();
            resetTelegramLinkStatus();
        }
    } catch (e) {
        ui.error = e.message;
    } finally {
        ui.loading = false;
    }
};

const ensureAuth = async () => {
    if (auth.token) {
        try {
            await auth.loadMe();
            return true;
        } catch {
            resetProtectedState();
            auth.clearAuth();
        }
    }

    await auth.waitForTelegramInitData();

    if (auth.hasTelegramInitData()) {
        try {
            const success = await auth.authWithTelegram();
            if (success) {
                await auth.loadMe();
                return true;
            }
        } catch (e) {
            ui.error = `Ошибка Telegram-авторизации: ${e.message}`;
        }
    }

    return false;
};

const clearTelegramLoginQuery = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (!url.searchParams.has('telegram_login')) {
        return;
    }

    url.searchParams.delete('telegram_login');
    const normalizedSearch = url.searchParams.toString();
    const normalizedUrl = `${url.pathname}${normalizedSearch ? `?${normalizedSearch}` : ''}${url.hash}`;
    window.history.replaceState({}, '', normalizedUrl);
};

const hydrateAuthFromTelegramCallback = async () => {
    if (typeof window === 'undefined') {
        return '';
    }

    const status = new URLSearchParams(window.location.search).get('telegram_login');

    if (!status) {
        return '';
    }

    if (status === 'error') {
        ui.error = 'Не удалось войти через Telegram. Попробуйте ещё раз.';
        clearTelegramLoginQuery();
        return 'error';
    }

    if (status !== 'success') {
        clearTelegramLoginQuery();
        return '';
    }

    try {
        await auth.completeTelegramSiteLoginFromSession();
        return 'success';
    } catch {
        ui.error = 'Не удалось войти через Telegram. Попробуйте ещё раз.';
        return 'error';
    } finally {
        clearTelegramLoginQuery();
    }
};

const loginFromWeb = async () => {
    auth.authLoading = true;
    auth.authError = '';
    ui.info = '';

    try {
        await auth.authWithPassword();
        await auth.loadMe();
        await loadData();
        ui.activeSidebarTab = 'catalog';
        auth.password = '';
        closeAuthModal();
    } catch (e) {
        auth.authError = e.message;
    } finally {
        auth.authLoading = false;
    }
};

const logout = async () => {
    if (auth.token) {
        try {
            await auth.requestLogout();
        } catch {
            // Local logout still removes the unusable session state.
        }
    }

    auth.clearAuth();
    resetProtectedState();
    resetTelegramLinkStatus();
    ui.closeProfileModal();
    ui.error = '';
    ui.info = '';
    auth.authError = '';
};

const closeProfileModal = () => {
    auth.profileError = '';
    telegramLinkError.value = '';
    ui.closeProfileModal();
};

const saveProfileFullName = async (fullName) => {
    if (!auth.token) {
        openAuthModal('Войдите, чтобы обновить профиль.');
        return;
    }

    ui.info = '';
    auth.profileError = '';

    try {
        await auth.updateProfile({ full_name: fullName });
        ui.info = 'Профиль обновлен.';
    } catch {
        // Error text is set in the auth store.
    }
};

const saveRequiredFullName = async (fullName) => {
    if (!auth.token) {
        return;
    }

    const validationError = validateRequiredFullName(fullName);

    if (validationError) {
        requiredFullNameError.value = validationError;
        return;
    }

    requiredFullNameSaving.value = true;
    requiredFullNameError.value = '';
    auth.profileError = '';

    try {
        await auth.updateProfile({ full_name: normalizeFullName(fullName) });
    } catch {
        requiredFullNameError.value = auth.profileError || 'Не удалось сохранить ФИО.';
    } finally {
        requiredFullNameSaving.value = false;
    }
};

const navigateToView = (view) => {
    if (view !== 'catalog' && !isAuthenticated.value) {
        openAuthModal('Войдите, чтобы открыть личный раздел.');
        return;
    }

    ui.activeSidebarTab = view;

    if (!isDesktopLayout.value) {
        if (view === 'catalog') {
            ui.closeMobilePanel();
        } else {
            ui.openMobilePanel(view);
        }
    } else {
        ui.closeMobilePanel();
    }
};

const openProtectedPanel = (panel) => {
    navigateToView(panel);
};

const openProfileFromMobileNav = () => {
    ui.closeMobilePanel();
    ui.openProfileModal();
};

const returnToCatalog = () => {
    navigateToView('catalog');

    nextTick(() => {
        document.getElementById('menu-heading')?.focus({ preventScroll: true });
        document.getElementById('menu-heading')?.scrollIntoView?.({ block: 'start' });
    });
};

const handleHeaderNavigate = (view) => {
    if (view === 'catalog') {
        clearCatalogFilters();
        ui.closeProfileModal();
        returnToCatalog();
        return;
    }

    navigateToView(view);
};

const showFavoritesFromProfile = () => {
    search.value = '';
    selectedCategory.value = null;
    favoritesOnly.value = true;
    ui.closeProfileModal();
    returnToCatalog();
};

const showCatalogFromProfile = () => {
    clearCatalogFilters();
    ui.closeProfileModal();
    returnToCatalog();
};

const openPanelFromProfile = (panel) => {
    ui.closeProfileModal();
    navigateToView(panel);
};

const clearCatalogFilters = () => {
    search.value = '';
    selectedCategory.value = null;
    favoritesOnly.value = false;
};

const toggleFavorite = (menuItemId) => {
    if (!isAuthenticated.value) {
        openAuthModal('Войдите, чтобы добавить блюдо в избранное.');
        return;
    }

    ui.toggleFavorite(menuItemId);
};

const editableOrderOrExplain = () => {
    if (canEditOrder.value) {
        return true;
    }

    ui.error = orderReadOnlyReason.value;
    return false;
};

const addItem = async (menuItemId) => {
    if (!auth.token) {
        openAuthModal('Войдите, чтобы добавить блюдо в заказ.');
        return;
    }

    if (!editableOrderOrExplain()) {
        return;
    }

    ui.actionLoading = true;
    ui.error = '';
    ui.info = '';

    try {
        await orderStore.addItem(auth.token, menuItemId);
    } catch (e) {
        if (isClosedOrderingErrorMessage(e.message)) {
            await syncClosedOrderingState();
        } else {
            ui.error = e.message;
        }
    } finally {
        ui.actionLoading = false;
    }
};

const changeQuantity = async (orderItem, quantity) => {
    if (!editableOrderOrExplain()) {
        return;
    }

    ui.actionLoading = true;
    ui.error = '';

    try {
        await orderStore.changeQuantity(auth.token, orderItem, quantity);
    } catch (e) {
        if (isClosedOrderingErrorMessage(e.message)) {
            await syncClosedOrderingState();
        } else {
            ui.error = e.message;
        }
    } finally {
        ui.actionLoading = false;
    }
};

const submitOrder = async () => {
    if (!editableOrderOrExplain()) {
        return;
    }

    ui.actionLoading = true;
    ui.error = '';
    ui.info = '';

    try {
        await orderStore.submitOrder(auth.token);
        reopenedForEditing.value = false;
    } catch (e) {
        if (isClosedOrderingErrorMessage(e.message)) {
            await syncClosedOrderingState();
        } else {
            ui.error = e.message;
        }
    } finally {
        ui.actionLoading = false;
    }
};

const reopenOrder = async () => {
    if (!auth.token) {
        return;
    }

    if (!canReopenSubmittedOrder.value) {
        ui.error = orderReadOnlyReason.value;
        return;
    }

    ui.actionLoading = true;
    ui.error = '';
    ui.info = '';

    try {
        await orderStore.reopenOrder(auth.token);
        reopenedForEditing.value = true;
    } catch (e) {
        if (isClosedOrderingErrorMessage(e.message)) {
            await syncClosedOrderingState(closedOrderingMessage);
        } else {
            ui.error = e.message;
        }
    } finally {
        ui.actionLoading = false;
    }
};

const repeatOrderFromHistory = async (historyOrder) => {
    if (!auth.token) {
        openAuthModal('Войдите, чтобы повторить заказ.');
        return;
    }

    if (!isOrderingWindowOpen.value) {
        ui.error = repeatWhenClosedMessage;
        return;
    }

    const hasDraftItems = order.value?.status === 'draft' && orderItems.value.length > 0;
    if (hasDraftItems && !window.confirm(repeatReplaceConfirmMessage)) {
        return;
    }

    ui.actionLoading = true;
    ui.error = '';
    ui.info = '';

    try {
        const response = await orderStore.repeatFromHistory(auth.token, historyOrder.id, 'replace');
        reopenedForEditing.value = false;

        const notices = [response.data?.message || 'Заказ добавлен в корзину.'];
        const skippedItems = Array.isArray(response.data?.skipped_items) ? response.data.skipped_items : [];
        if (skippedItems.length > 0) {
            notices.push(`Некоторые блюда сейчас недоступны: ${skippedItems.join(', ')}.`);
        }
        if (response.data?.warning) {
            notices.push(response.data.warning);
        }

        ui.info = notices.join(' ');
        ui.closeProfileModal();
        navigateToView('order');
    } catch (e) {
        if (isClosedOrderingErrorMessage(e.message)) {
            await syncClosedOrderingState(closedOrderingMessage);
            ui.error = repeatWhenClosedMessage;
        } else {
            ui.error = e.message;
        }
    } finally {
        ui.actionLoading = false;
    }
};

const eatOneFromFridge = async (fridgeItemId) => {
    ui.actionLoading = true;
    ui.error = '';

    try {
        await fridge.eatOne(auth.token, fridgeItemId);
        ui.info = 'Холодильник обновлен.';
    } catch (e) {
        ui.error = e.message;
    } finally {
        ui.actionLoading = false;
    }
};

const eatAllFromFridge = async (fridgeItemId) => {
    ui.actionLoading = true;
    ui.error = '';

    try {
        await fridge.eatAll(auth.token, fridgeItemId);
        ui.info = 'Позиция отмечена как съеденная.';
    } catch (e) {
        ui.error = e.message;
    } finally {
        ui.actionLoading = false;
    }
};

const discardFromFridge = async (fridgeItemId) => {
    ui.actionLoading = true;
    ui.error = '';

    try {
        await fridge.discard(auth.token, fridgeItemId);
        ui.info = 'Позиция списана.';
    } catch (e) {
        ui.error = e.message;
    } finally {
        ui.actionLoading = false;
    }
};

onMounted(async () => {
    window.Telegram?.WebApp?.ready();
    window.Telegram?.WebApp?.expand();

    const telegramCallbackResult = await hydrateAuthFromTelegramCallback();
    const telegramCallbackError = ui.error;

    await ensureAuth();
    await loadData();

    if (telegramCallbackResult === 'error' && telegramCallbackError && !ui.error) {
        ui.error = telegramCallbackError;
    }

    if (isAuthenticated.value && activeSidebarTab.value === 'order') {
        ui.activeSidebarTab = 'catalog';
    }
});

onBeforeUnmount(() => {
    clearDeadlineRefreshTimer();
});
</script>

<template>
    <div class="customer-app min-h-dvh overflow-x-clip bg-[#f2f2f2] text-slate-900">
        <AppHeader
            :loading="loading"
            :is-authenticated="isAuthenticated"
            :total-positions="totalPositions"
            :active-fridge-items-count="activeFridgeItemsCount"
            :display-user-name="displayUserName"
            :active-view="activeSidebarTab"
            v-model:search="search"
            @open-auth="openAuthModal()"
            @navigate="handleHeaderNavigate"
            @open-profile="ui.openProfileModal"
        />

        <main class="page-shell app-main-shell pt-2.5 sm:pt-3.5" :class="isAuthenticated ? 'pb-24 xl:pb-3' : 'pb-8 xl:pb-3'">
            <Alert
                v-if="error && !mobilePanel"
                variant="destructive"
                class="mb-4 rounded-xl border-red-200 bg-red-50 text-red-700"
                role="alert"
                aria-live="assertive"
            >
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <Alert
                v-if="visibleInfo"
                class="mb-4 rounded-xl border-blue-100 bg-blue-50 text-blue-800"
                role="status"
                aria-live="polite"
            >
                <AlertDescription>{{ visibleInfo }}</AlertDescription>
            </Alert>

            <div
                class="catalog-layout mt-0"
                :class="isCatalogView ? (isAuthenticated ? 'catalog-layout--auth-cart' : 'catalog-layout--guest-cart') : 'catalog-layout--single'"
            >
                <MenuGrid
                    v-if="isCatalogView"
                    v-model:search="search"
                    v-model:selected-category="selectedCategory"
                    :loading="loading"
                    :categories="categories"
                    :items="items"
                    :filtered-items="displayedItems"
                    :menu-skeleton-rows="menuSkeletonRows"
                    :order-item-by-menu-item="orderItemByMenuItem"
                    :favorite-ids="favoriteIds"
                    :favorites-only="favoritesOnly"
                    :favorites-count="favoritesCount"
                    :is-authenticated="isAuthenticated"
                    :can-edit-order="canEditOrder"
                    :disabled-reason="orderReadOnlyReason"
                    :action-loading="actionLoading"
                    :has-active-filters="hasActiveFilters"
                    @toggle-favorite="toggleFavorite"
                    @toggle-favorites-filter="ui.toggleFavoritesFilter"
                    @clear-filters="clearCatalogFilters"
                    @add-item="addItem"
                    @change-quantity="changeQuantity"
                />

                <Card
                    v-else-if="isOrderView"
                    class="mx-auto h-full min-h-0 w-full max-w-[52rem] gap-0 overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white py-0 shadow-sm"
                >
                    <CardContent class="flex h-full min-h-0 p-0">
                        <OrderPanel
                            :order="order"
                            :order-items="orderItems"
                            :menu-items-by-id="menuItemsById"
                            :total-positions="totalPositions"
                            :panel-title="'Мой заказ'"
                            :status-line="cartStatusBadgeText"
                            :status-detail="cartStatusDetailText"
                            :disabled-checkout-label="disabledCheckoutLabel"
                            :disabled-checkout-helper="disabledCheckoutHelper"
                            :empty-state-detail="emptyCartDetail"
                            :can-edit-order="canEditOrder"
                            :can-reopen-order="canReopenSubmittedOrder"
                            :loading="loading"
                            :action-loading="actionLoading"
                            :order-skeleton-rows="orderSkeletonRows"
                            @change-quantity="changeQuantity"
                            @reopen-order="reopenOrder"
                            @submit-order="submitOrder"
                        />
                    </CardContent>
                </Card>

                <Card
                    v-else-if="isFridgeView"
                    class="mx-auto h-full min-h-0 w-full max-w-[64rem] gap-0 overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white py-0 shadow-sm"
                >
                    <CardContent class="flex h-full min-h-0 p-0">
                        <FridgePanel
                            :fridge-items="fridgeItems"
                            :fridge-meta="fridgeMeta"
                            :fridge-loading="loading || fridgeLoading"
                            :action-loading="actionLoading"
                            :active-fridge-items-count="activeFridgeItemsCount"
                            :order-skeleton-rows="orderSkeletonRows"
                            @eat-one="eatOneFromFridge"
                            @eat-all="eatAllFromFridge"
                            @discard="discardFromFridge"
                        />
                    </CardContent>
                </Card>

                <Card
                    v-else-if="isHistoryView"
                    class="h-full min-h-0 gap-0 overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white py-0 shadow-sm"
                >
                    <CardContent class="flex h-full min-h-0 p-0">
                        <HistoryPanel
                            :fridge-history="fridgeHistory"
                            :fridge-loading="loading || fridgeLoading"
                            :order-skeleton-rows="orderSkeletonRows"
                        />
                    </CardContent>
                </Card>

                <Card
                    v-if="isCatalogView"
                    data-testid="desktop-order-panel"
                    aria-label="Панель корзины"
                    class="catalog-order-panel hidden min-h-0 gap-0 overflow-hidden rounded-[1.5rem] border border-slate-200/70 bg-white py-0 text-slate-900 shadow-none xl:block xl:sticky xl:top-[5.75rem] xl:h-full xl:max-h-full"
                >
                    <CardContent class="flex h-full min-h-0 flex-col p-0">
                        <OrderPanel
                            :order="order"
                            :order-items="orderItems"
                            :menu-items-by-id="menuItemsById"
                            :total-positions="totalPositions"
                            :panel-title="'Корзина'"
                            compact-cart
                            :status-line="cartStatusBadgeText"
                            :status-detail="cartStatusDetailText"
                            :disabled-checkout-label="disabledCheckoutLabel"
                            :disabled-checkout-helper="disabledCheckoutHelper"
                            :empty-state-detail="emptyCartDetail"
                            :is-authenticated="isAuthenticated"
                            :can-edit-order="isAuthenticated ? canEditOrder : false"
                            :can-reopen-order="isAuthenticated ? canReopenSubmittedOrder : false"
                            :loading="loading"
                            :action-loading="actionLoading"
                            :error="error"
                            :order-skeleton-rows="orderSkeletonRows"
                            @open-auth="openAuthModal('Войдите, чтобы оформить заказ.')"
                            @change-quantity="changeQuantity"
                            @reopen-order="reopenOrder"
                            @submit-order="submitOrder"
                        />
                    </CardContent>
                </Card>
            </div>
        </main>

        <MobileBottomNav
            v-if="isAuthenticated && !mobilePanel"
            :active-panel="mobilePanel"
            :total-positions="totalPositions"
            :active-fridge-items-count="activeFridgeItemsCount"
            :is-profile-open="isProfileModalOpen"
            @catalog="returnToCatalog"
            @order="openProtectedPanel('order')"
            @fridge="openProtectedPanel('fridge')"
            @profile="openProfileFromMobileNav"
        />

        <MobilePanelSheet
            v-if="isAuthenticated"
            :open="mobilePanel === 'order'"
            title="Мой заказ"
            :description="orderPanelDescription"
            close-label="Закрыть мой заказ"
            test-id="mobile-order-panel"
            @close="ui.closeMobilePanel"
        >
            <OrderPanel
                :order="order"
                :order-items="orderItems"
                :menu-items-by-id="menuItemsById"
                :total-positions="totalPositions"
                :show-heading="false"
                :status-line="cartStatusBadgeText"
                :status-detail="cartStatusDetailText"
                :disabled-checkout-label="disabledCheckoutLabel"
                :disabled-checkout-helper="disabledCheckoutHelper"
                :empty-state-detail="emptyCartDetail"
                :can-edit-order="canEditOrder"
                :can-reopen-order="canReopenSubmittedOrder"
                :loading="loading"
                :action-loading="actionLoading"
                :error="error"
                :order-skeleton-rows="orderSkeletonRows"
                @change-quantity="changeQuantity"
                @reopen-order="reopenOrder"
                @submit-order="submitOrder"
            />
        </MobilePanelSheet>

        <MobilePanelSheet
            v-if="isAuthenticated"
            :open="mobilePanel === 'fridge'"
            title="Мой холодильник"
            description="Блюда, которые сейчас ждут вас."
            close-label="Закрыть холодильник"
            test-id="mobile-fridge-panel"
            @close="ui.closeMobilePanel"
        >
            <FridgePanel
                :fridge-items="fridgeItems"
                :fridge-meta="fridgeMeta"
                :fridge-loading="loading || fridgeLoading"
                :action-loading="actionLoading"
                :error="error"
                :active-fridge-items-count="activeFridgeItemsCount"
                :show-heading="false"
                :order-skeleton-rows="orderSkeletonRows"
                @eat-one="eatOneFromFridge"
                @eat-all="eatAllFromFridge"
                @discard="discardFromFridge"
            />
        </MobilePanelSheet>

        <MobilePanelSheet
            v-if="isAuthenticated"
            :open="mobilePanel === 'history'"
            title="Моя история"
            description="Последние действия с блюдами."
            close-label="Закрыть историю питания"
            test-id="mobile-history-panel"
            @close="ui.closeMobilePanel"
        >
            <HistoryPanel
                :fridge-history="fridgeHistory"
                :fridge-loading="loading || fridgeLoading"
                :show-heading="false"
                :order-skeleton-rows="orderSkeletonRows"
            />
        </MobilePanelSheet>

        <LoginModal
            :open="isAuthModalOpen"
            :email="email"
            :password="password"
            :remember-me="rememberMe"
            :show-password="showPassword"
            :loading="authLoading"
            :error="authError"
            :message="authModalMessage"
            @close="closeAuthModal"
            @submit="loginFromWeb"
            @update:email="auth.email = $event"
            @update:password="auth.password = $event"
            @update:remember-me="auth.rememberMe = $event"
            @update:show-password="auth.showPassword = $event"
        />

        <UserProfileModal
            :open="isProfileModalOpen"
            :user="me"
            :favorites-count="favoritesCount"
            :profile-saving="profileSaving"
            :profile-error="profileError"
            :order-history="orderHistory"
            :order-history-loading="orderHistoryLoading"
            :order-history-error="orderHistoryError"
            :can-repeat-history="isOrderingWindowOpen"
            :repeat-action-loading="actionLoading"
            @close="closeProfileModal"
            :telegram-linked="telegramLinkStatus.linked"
            :telegram-link-available="telegramLinkStatus.link_available"
            :telegram-loading="telegramLinkLoading"
            :telegram-error="telegramLinkError"
            @logout="logout"
            @show-favorites="showFavoritesFromProfile"
            @show-catalog="showCatalogFromProfile"
            @show-order="openPanelFromProfile('order')"
            @show-fridge="openPanelFromProfile('fridge')"
            @show-history="openPanelFromProfile('history')"
            @save-full-name="saveProfileFullName"
            @telegram-link="linkTelegramFromProfile"
            @telegram-open-bot="openTelegramBotLink"
            @repeat-order="repeatOrderFromHistory"
        />

        <RequiredFullNameModal
            :open="requiredFullNameModalOpen"
            :saving="requiredFullNameSaving"
            :error="requiredFullNameError"
            :initial-value="requiredFullNameDraft"
            @save="saveRequiredFullName"
        />
    </div>
</template>
