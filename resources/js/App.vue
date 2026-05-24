<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { storeToRefs } from 'pinia';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppHeader from '@/components/AppHeader.vue';
import CategorySidebar from '@/components/CategorySidebar.vue';
import FridgePanel from '@/components/FridgePanel.vue';
import HistoryPanel from '@/components/HistoryPanel.vue';
import LoginModal from '@/components/LoginModal.vue';
import MenuGrid from '@/components/MenuGrid.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import MobilePanelSheet from '@/components/MobilePanelSheet.vue';
import OrderPanel from '@/components/OrderPanel.vue';
import UserProfileModal from '@/components/UserProfileModal.vue';
import WeekStatus from '@/components/WeekStatus.vue';
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

const {
    me,
    email,
    password,
    rememberMe,
    showPassword,
    authLoading,
    authError,
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
    orderItems,
    totalPositions,
    orderItemByMenuItem,
} = storeToRefs(orderStore);

const {
    fridgeItems,
    fridgeHistory,
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

const menuSkeletonRows = Array.from({ length: 6 }, (_, index) => index + 1);
const orderSkeletonRows = Array.from({ length: 3 }, (_, index) => index + 1);

const menuItemsById = computed(() => new Map(items.value.map((item) => [item.id, item])));
const isSubmittedOrder = computed(() => order.value?.status === 'submitted');
const canReopenSubmittedOrder = computed(() => Boolean(order.value?.can_reopen_for_editing));
const canEditOrder = computed(() => isOpenForOrdering.value && !isSubmittedOrder.value);

const orderPanelDescription = computed(() => {
    if (isSubmittedOrder.value) {
        return orderReadOnlyReason.value;
    }

    if (!canEditOrder.value) {
        return 'Редактирование завершено. Проверьте позиции заказа.';
    }

    return 'Проверьте позиции и отправьте заказ до дедлайна.';
});

const orderReadOnlyReason = computed(() => {
    if (isSubmittedOrder.value) {
        if (canReopenSubmittedOrder.value) {
            return 'Заказ отправлен. Его можно изменить до дедлайна.';
        }

        if (cycle.value?.deadline_passed) {
            return 'Заказ отправлен. Дедлайн прошел, изменения недоступны.';
        }

        return 'Заказ отправлен. Изменения больше недоступны.';
    }

    return availabilityDescription.value || 'Прием заказов завершен.';
});

const displayedItems = computed(() => {
    if (!favoritesOnly.value) {
        return filteredItems.value;
    }

    return filteredItems.value.filter((item) => favoriteIds.value.has(item.id));
});

const hasActiveFilters = computed(() => {
    return Boolean(search.value.trim() || selectedCategory.value !== null || favoritesOnly.value);
});

watch(isDesktopLayout, (desktop) => {
    if (desktop) {
        ui.closeMobilePanel();
    }
});

const resetProtectedState = () => {
    orderStore.resetOrder();
    fridge.resetFridge();
    ui.resetSessionUi();
};

const openAuthModal = (message = '') => {
    auth.authError = '';
    ui.openAuthModal(message);
};

const closeAuthModal = () => {
    auth.authError = '';
    ui.closeAuthModal();
};

const loadData = async () => {
    ui.loading = true;
    ui.error = '';

    try {
        await catalog.loadCatalogData();

        if (auth.token) {
            await Promise.all([
                orderStore.loadCurrentOrder(auth.token),
                fridge.loadFridgeData(auth.token),
            ]);
        } else {
            orderStore.resetOrder();
            fridge.resetFridge();
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

const loginFromWeb = async () => {
    auth.authLoading = true;
    auth.authError = '';
    ui.info = '';

    try {
        await auth.authWithPassword();
        await auth.loadMe();
        await loadData();
        ui.activeSidebarTab = 'order';
        auth.password = '';
        closeAuthModal();
    } catch (e) {
        auth.authError = e.message;
    } finally {
        auth.authLoading = false;
    }
};

const loginFromTelegram = async () => {
    auth.authLoading = true;
    auth.authError = '';
    ui.info = '';

    try {
        const success = await auth.authWithTelegram();
        if (!success) {
            auth.authError = 'Откройте страницу через кнопку /menu в Telegram.';
            return;
        }

        await auth.loadMe();
        await loadData();
        ui.activeSidebarTab = 'order';
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
    ui.closeProfileModal();
    ui.error = '';
    ui.info = '';
    auth.authError = '';
};

const openProtectedPanel = (panel) => {
    if (!isAuthenticated.value) {
        openAuthModal('Войдите, чтобы открыть личный раздел.');
        return;
    }

    ui.activeSidebarTab = panel;

    if (!isDesktopLayout.value) {
        ui.openMobilePanel(panel);
    } else {
        ui.closeMobilePanel();
    }
};

const returnToCatalog = () => {
    ui.closeMobilePanel();
    nextTick(() => {
        document.getElementById('menu-heading')?.focus({ preventScroll: true });
        document.getElementById('menu-heading')?.scrollIntoView?.({ block: 'start' });
    });
};

const showFavoritesFromProfile = () => {
    search.value = '';
    selectedCategory.value = null;
    favoritesOnly.value = true;
    ui.closeProfileModal();
    returnToCatalog();
};

const openPanelFromProfile = (panel) => {
    ui.closeProfileModal();
    openProtectedPanel(panel);
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
        ui.error = e.message;
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
        ui.error = e.message;
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
        ui.info = 'Заказ подтвержден.';
    } catch (e) {
        ui.error = e.message;
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
        ui.info = 'Заказ снова открыт для редактирования.';
    } catch (e) {
        ui.error = e.message;
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
        ui.info = 'Позиция отмечена как выброшенная.';
    } catch (e) {
        ui.error = e.message;
    } finally {
        ui.actionLoading = false;
    }
};

onMounted(async () => {
    window.Telegram?.WebApp?.ready();
    window.Telegram?.WebApp?.expand();

    await ensureAuth();
    await loadData();
});
</script>

<template>
    <div class="min-h-dvh bg-slate-50 text-slate-900">
        <AppHeader
            :loading="loading"
            :is-authenticated="isAuthenticated"
            :total-positions="totalPositions"
            :active-fridge-items-count="activeFridgeItemsCount"
            :display-user-name="displayUserName"
            @open-auth="openAuthModal()"
            @open-order="openProtectedPanel('order')"
            @open-fridge="openProtectedPanel('fridge')"
            @open-profile="ui.openProfileModal"
        />

        <main class="page-shell pt-4 sm:pt-6" :class="isAuthenticated ? 'pb-24 xl:pb-10' : 'pb-8 xl:pb-10'">
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
                v-if="info"
                class="mb-4 rounded-xl border-blue-100 bg-blue-50 text-blue-800"
                role="status"
                aria-live="polite"
            >
                <AlertDescription>{{ info }}</AlertDescription>
            </Alert>

            <WeekStatus
                :loading="loading"
                :cycle="cycle"
                :weekly-deadline-label="weeklyDeadlineLabel"
                :is-open-for-ordering="isOpenForOrdering"
                :availability-label="availabilityLabel"
                :availability-description="availabilityDescription"
            />

            <div
                class="catalog-layout mt-5 sm:mt-6"
                :class="isAuthenticated ? 'catalog-layout--auth' : 'catalog-layout--guest'"
            >
                <CategorySidebar
                    :loading="loading"
                    :categories="categories"
                    :items="items"
                    v-model:selected-category="selectedCategory"
                />

                <MenuGrid
                    v-model:search="search"
                    :loading="loading"
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

                <Card v-if="isAuthenticated" class="catalog-order-panel hidden min-h-0 overflow-y-auto rounded-3xl border border-slate-200 bg-white text-slate-900 shadow-sm xl:sticky xl:top-24 xl:block xl:h-[calc(100dvh-7rem)] xl:max-h-[calc(100dvh-7rem)] xl:overscroll-contain">
                    <CardContent class="flex h-full min-h-0 flex-col p-0">
                        <Tabs v-model="activeSidebarTab" class="flex h-full min-h-0 w-full flex-col">
                            <TabsList :aria-busy="loading" class="grid h-14 w-full shrink-0 grid-cols-3 rounded-none border-b border-slate-200 bg-white p-1">
                                <TabsTrigger value="order" class="gap-1 rounded-lg px-1 text-xs font-medium text-slate-500 data-active:bg-slate-100 data-active:text-slate-950 data-active:shadow-none">
                                    Заказ
                                    <Skeleton v-if="loading" class="size-5 rounded-full bg-slate-100" />
                                    <Badge v-else class="rounded-full bg-blue-700 px-2 text-xs font-medium tabular-nums text-white">{{ totalPositions }}</Badge>
                                </TabsTrigger>
                                <TabsTrigger value="fridge" class="gap-1 rounded-lg px-1 text-xs font-medium text-slate-500 data-active:bg-slate-100 data-active:text-slate-950 data-active:shadow-none">
                                    Холодильник
                                    <Skeleton v-if="loading" class="size-5 rounded-full bg-slate-100" />
                                    <Badge v-else class="rounded-full bg-slate-200 px-2 text-xs font-medium tabular-nums text-slate-700">{{ activeFridgeItemsCount }}</Badge>
                                </TabsTrigger>
                                <TabsTrigger value="history" class="rounded-lg px-1 text-xs font-medium text-slate-500 data-active:bg-slate-100 data-active:text-slate-950 data-active:shadow-none">
                                    История
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="order" class="mt-0 flex min-h-0 flex-1 overflow-y-auto overscroll-contain p-0">
                                <OrderPanel
                                    :order="order"
                                    :order-items="orderItems"
                                    :menu-items-by-id="menuItemsById"
                                    :total-positions="totalPositions"
                                    :can-edit-order="canEditOrder"
                                    :can-reopen-order="canReopenSubmittedOrder"
                                    :read-only-reason="orderReadOnlyReason"
                                    :loading="loading"
                                    :action-loading="actionLoading"
                                    :weekly-deadline-label="weeklyDeadlineLabel"
                                    :order-skeleton-rows="orderSkeletonRows"
                                    @change-quantity="changeQuantity"
                                    @reopen-order="reopenOrder"
                                    @submit-order="submitOrder"
                                />
                            </TabsContent>

                            <TabsContent value="fridge" class="mt-0 min-h-0 flex-1 overflow-y-auto overscroll-contain p-0">
                                <FridgePanel
                                    :fridge-items="fridgeItems"
                                    :fridge-loading="loading || fridgeLoading"
                                    :action-loading="actionLoading"
                                    :active-fridge-items-count="activeFridgeItemsCount"
                                    :order-skeleton-rows="orderSkeletonRows"
                                    @eat-one="eatOneFromFridge"
                                    @eat-all="eatAllFromFridge"
                                    @discard="discardFromFridge"
                                />
                            </TabsContent>
                            <TabsContent value="history" class="mt-0 min-h-0 flex-1 overflow-y-auto overscroll-contain p-0">
                                <HistoryPanel
                                    :fridge-history="fridgeHistory"
                                    :fridge-loading="loading || fridgeLoading"
                                    :order-skeleton-rows="orderSkeletonRows"
                                />
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </div>
        </main>

        <MobileBottomNav
            v-if="isAuthenticated"
            :active-panel="mobilePanel"
            :total-positions="totalPositions"
            :active-fridge-items-count="activeFridgeItemsCount"
            @catalog="returnToCatalog"
            @order="openProtectedPanel('order')"
            @fridge="openProtectedPanel('fridge')"
            @history="openProtectedPanel('history')"
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
                :can-edit-order="canEditOrder"
                :can-reopen-order="canReopenSubmittedOrder"
                :read-only-reason="orderReadOnlyReason"
                :loading="loading"
                :action-loading="actionLoading"
                :error="error"
                :weekly-deadline-label="weeklyDeadlineLabel"
                :order-skeleton-rows="orderSkeletonRows"
                @change-quantity="changeQuantity"
                @reopen-order="reopenOrder"
                @submit-order="submitOrder"
            />
        </MobilePanelSheet>

        <MobilePanelSheet
            v-if="isAuthenticated"
            :open="mobilePanel === 'fridge'"
            title="Холодильник"
            description="Доставленные блюда и история действий."
            close-label="Закрыть холодильник"
            test-id="mobile-fridge-panel"
            @close="ui.closeMobilePanel"
        >
            <FridgePanel
                :fridge-items="fridgeItems"
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
            title="История питания"
            description="Съеденные и списанные блюда из холодильника."
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
            :show-telegram="auth.hasTelegramInitData()"
            @close="closeAuthModal"
            @submit="loginFromWeb"
            @telegram-login="loginFromTelegram"
            @update:email="auth.email = $event"
            @update:password="auth.password = $event"
            @update:remember-me="auth.rememberMe = $event"
            @update:show-password="auth.showPassword = $event"
        />

        <UserProfileModal
            :open="isProfileModalOpen"
            :user="me"
            :favorites-count="favoritesCount"
            @close="ui.closeProfileModal"
            @logout="logout"
            @show-favorites="showFavoritesFromProfile"
            @show-order="openPanelFromProfile('order')"
            @show-fridge="openPanelFromProfile('fridge')"
            @show-history="openPanelFromProfile('history')"
        />
    </div>
</template>
