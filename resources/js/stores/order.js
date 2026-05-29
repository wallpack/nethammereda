import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import {
    addOrderItem,
    deleteOrderItem,
    fetchMyOrder,
    reopenMyOrder,
    submitMyOrder,
    updateOrderItemQuantity,
} from '@/api/order';
import { useCatalogStore } from '@/stores/catalog';
import { orderStatusLabel } from '@/lib/formatters';

export const useOrderStore = defineStore('order', () => {
    const order = ref(null);
    const orderNotice = ref('');

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
    };

    return {
        order,
        orderNotice,
        orderItems,
        totalPositions,
        orderItemByMenuItem,
        ordersCount,
        lastOrderLabel,
        setOrderFromResponse,
        loadCurrentOrder,
        addItem,
        changeQuantity,
        submitOrder,
        reopenOrder,
        clearOrder,
        resetOrder,
    };
});
