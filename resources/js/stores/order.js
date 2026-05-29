import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import {
    addOrderItem,
    deleteOrderItem,
    fetchOrderHistory,
    fetchMyOrder,
    repeatOrder,
    reopenMyOrder,
    submitMyOrder,
    updateOrderItemQuantity,
} from '@/api/order';
import { useCatalogStore } from '@/stores/catalog';
import { orderStatusLabel } from '@/lib/formatters';

export const useOrderStore = defineStore('order', () => {
    const order = ref(null);
    const orderNotice = ref('');
    const orderHistory = ref([]);
    const orderHistoryLoading = ref(false);
    const orderHistoryError = ref('');

    const orderItems = computed(() => order.value?.items ?? []);

    const totalPositions = computed(() => {
        return orderItems.value.reduce((sum, item) => sum + Number(item.quantity), 0);
    });

    const orderItemByMenuItem = computed(() => {
        const map = new Map();

        for (const item of orderItems.value) {
            map.set(item.menu_item_id, item);
        }

        return map;
    });

    const ordersCount = computed(() => (order.value ? 1 : 0));

    const lastOrderLabel = computed(() => {
        if (!order.value?.status) {
            return '';
        }

        return `Текущий заказ: ${orderStatusLabel(order.value.status)}`;
    });

    const setOrderFromResponse = (payload) => {
        order.value = payload?.data ?? payload ?? null;
        orderNotice.value = '';
    };

    const loadCurrentOrder = async (token) => {
        const response = await fetchMyOrder(token);
        const catalog = useCatalogStore();

        catalog.cycle = response.data?.cycle ?? catalog.cycle;
        const cycle = response.data?.cycle ?? null;
        const rawOrder = response.data?.order ?? null;
        const canOrder = Boolean(
            cycle?.can_order
            ?? cycle?.is_orderable
            ?? cycle?.is_open_for_ordering,
        );
        const isDraft = rawOrder?.status === 'draft';
        const draftUnavailable = Boolean(response.data?.draft_unavailable);
        const draftUnavailableMessage = response.data?.draft_unavailable_message ?? '';

        if (draftUnavailable || (!canOrder && isDraft)) {
            order.value = null;
            orderNotice.value = draftUnavailableMessage || 'Цикл закрыт, черновик заказа больше недоступен.';
        } else {
            order.value = rawOrder;
            orderNotice.value = '';
        }

        return response;
    };

    const loadOrderHistory = async (token) => {
        orderHistoryLoading.value = true;
        orderHistoryError.value = '';

        try {
            const response = await fetchOrderHistory(token);
            orderHistory.value = Array.isArray(response.data) ? response.data : [];

            return response;
        } catch (error) {
            orderHistory.value = [];
            orderHistoryError.value = error.message;
            throw error;
        } finally {
            orderHistoryLoading.value = false;
        }
    };

    const addItem = async (token, menuItemId) => {
        const response = await addOrderItem(token, menuItemId, 1);
        setOrderFromResponse(response);

        return response;
    };

    const changeQuantity = async (token, orderItem, quantity) => {
        if (quantity <= 0) {
            const response = await deleteOrderItem(token, orderItem.id);
            setOrderFromResponse(response);

            return response;
        }

        const response = await updateOrderItemQuantity(token, orderItem.id, quantity);
        setOrderFromResponse(response);

        return response;
    };

    const submitOrder = async (token) => {
        const response = await submitMyOrder(token);
        setOrderFromResponse(response);

        return response;
    };

    const reopenOrder = async (token) => {
        const response = await reopenMyOrder(token);
        setOrderFromResponse(response);

        return response;
    };

    const repeatFromHistory = async (token, historyOrderId, mode = 'replace') => {
        const response = await repeatOrder(token, historyOrderId, mode);
        order.value = response.data?.order ?? null;
        orderNotice.value = '';

        return response;
    };

    const clearOrder = async (token) => {
        if (!orderItems.value.length) {
            return null;
        }

        let latestOrder = order.value;

        for (const item of orderItems.value) {
            const response = await deleteOrderItem(token, item.id);
            latestOrder = response?.data ?? response ?? latestOrder;
        }

        order.value = latestOrder;

        return latestOrder;
    };

    const resetOrder = () => {
        order.value = null;
        orderNotice.value = '';
        orderHistory.value = [];
        orderHistoryLoading.value = false;
        orderHistoryError.value = '';
    };

    return {
        order,
        orderNotice,
        orderHistory,
        orderHistoryLoading,
        orderHistoryError,
        orderItems,
        totalPositions,
        orderItemByMenuItem,
        ordersCount,
        lastOrderLabel,
        setOrderFromResponse,
        loadCurrentOrder,
        loadOrderHistory,
        addItem,
        changeQuantity,
        submitOrder,
        reopenOrder,
        repeatFromHistory,
        clearOrder,
        resetOrder,
    };
});
