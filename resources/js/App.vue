<script setup>
import { computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppHeader from '@/components/AppHeader.vue';
import CategorySidebar from '@/components/CategorySidebar.vue';
import FridgePanel from '@/components/FridgePanel.vue';
import LoginModal from '@/components/LoginModal.vue';
import MenuGrid from '@/components/MenuGrid.vue';
import OrderPanel from '@/components/OrderPanel.vue';
import UserProfileModal from '@/components/UserProfileModal.vue';
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
    weeklyDeadlineLabel,
} = storeToRefs(catalog);

const {
    order,
    orderItems,
    totalPositions,
    orderItemByMenuItem,
    ordersCount,
    lastOrderLabel,
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
    profileNotice,
    favoriteIds,
    favoritesCount,
} = storeToRefs(ui);

const menuSkeletonRows = Array.from({ length: 6 }, (_, index) => index + 1);
const orderSkeletonRows = Array.from({ length: 3 }, (_, index) => index + 1);

const menuItemsById = computed(() => {
    const map = new Map();

    for (const item of items.value) {
        map.set(item.id, item);
    }

    return map;
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
        }
    }

    auth.clearAuth();
    resetProtectedState();
    ui.closeProfileModal();
    ui.error = '';
    ui.info = '';
    auth.authError = '';
};

const toggleFavorite = (menuItemId) => {
    if (!isAuthenticated.value) {
        openAuthModal('Войдите, чтобы добавить блюдо в избранное.');
        return;
    }

    ui.toggleFavorite(menuItemId);
};

const addItem = async (menuItemId) => {
    if (!auth.token) {
        openAuthModal('Войдите, чтобы добавить блюдо в заказ.');
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

const clearOrder = async () => {
    if (!orderItems.value.length) {
        return;
    }

    ui.actionLoading = true;
    ui.error = '';
    ui.info = '';

    try {
        await orderStore.clearOrder(auth.token);
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
    <div class="min-h-screen bg-[#f8faff] text-slate-900">
        <AppHeader
            :cycle="cycle"
            :weekly-deadline-label="weeklyDeadlineLabel"
            :is-open-for-ordering="isOpenForOrdering"
            :is-authenticated="isAuthenticated"
            :total-positions="totalPositions"
            :display-user-name="displayUserName"
            @open-auth="openAuthModal()"
            @open-profile="ui.openProfileModal"
        />

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
            <CategorySidebar
                :categories="categories"
                :items="items"
                v-model:selected-category="selectedCategory"
            />

            <MenuGrid
                v-model:search="search"
                :loading="loading"
                :filtered-items="filteredItems"
                :menu-skeleton-rows="menuSkeletonRows"
                :order-item-by-menu-item="orderItemByMenuItem"
                :favorite-ids="favoriteIds"
                :is-authenticated="isAuthenticated"
                :is-open-for-ordering="isOpenForOrdering"
                :action-loading="actionLoading"
                @toggle-favorite="toggleFavorite"
                @add-item="addItem"
                @change-quantity="changeQuantity"
            />

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

                        <TabsContent value="order" class="mt-0 flex min-h-0 flex-1 p-0">
                            <OrderPanel
                                :order="order"
                                :order-items="orderItems"
                                :menu-items-by-id="menuItemsById"
                                :total-positions="totalPositions"
                                :is-open-for-ordering="isOpenForOrdering"
                                :loading="loading"
                                :action-loading="actionLoading"
                                :weekly-deadline-label="weeklyDeadlineLabel"
                                :order-skeleton-rows="orderSkeletonRows"
                                @clear-order="clearOrder"
                                @change-quantity="changeQuantity"
                                @submit-order="submitOrder"
                            />
                        </TabsContent>

                        <TabsContent value="fridge" class="mt-0 min-h-0 flex-1 p-0">
                            <FridgePanel
                                :fridge-items="fridgeItems"
                                :fridge-history="fridgeHistory"
                                :fridge-loading="fridgeLoading"
                                :action-loading="actionLoading"
                                :active-fridge-items-count="activeFridgeItemsCount"
                                :order-skeleton-rows="orderSkeletonRows"
                                @eat-one="eatOneFromFridge"
                                @eat-all="eatAllFromFridge"
                                @discard="discardFromFridge"
                            />
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </section>

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
            :orders-count="ordersCount"
            :last-order-label="lastOrderLabel"
            :notice="profileNotice"
            @close="ui.closeProfileModal"
            @logout="logout"
            @show-favorites="ui.showProfileNotice('Раздел избранного подготовлен. Полноценная страница появится после API избранного.')"
            @show-orders="ui.showProfileNotice('Мои заказы будут связаны с историей заказов после появления отдельного API.')"
            @show-settings="ui.showProfileNotice('Настройки профиля пока не подключены.')"
        />
    </div>
</template>
