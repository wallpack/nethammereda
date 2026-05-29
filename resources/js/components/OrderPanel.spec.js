import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrderPanel from './OrderPanel.vue';

const historyOrder = {
    id: 91,
    status: 'submitted',
    submitted_at: '2026-05-28T10:00:00.000000Z',
    total_price: '500.00',
    items_count: 2,
    can_repeat: true,
    items: [
        {
            id: 901,
            menu_item_id: 11,
            title: 'Soup #1',
            quantity: 1,
            unit_price: '250.00',
            total_price: '250.00',
        },
        {
            id: 902,
            menu_item_id: 12,
            title: 'Pasta #2',
            quantity: 1,
            unit_price: '250.00',
            total_price: '250.00',
        },
    ],
};

const baseProps = {
    order: {
        id: 100,
        status: 'draft',
        total_price: '0.00',
    },
    orderItems: [],
    menuItemsById: new Map(),
    totalPositions: 0,
    isAuthenticated: true,
    showHeading: true,
    panelTitle: 'Мой заказ',
    statusLine: '',
    canEditOrder: false,
    canReopenOrder: false,
    loading: false,
    actionLoading: false,
    error: '',
    orderSkeletonRows: [1, 2],
    orderHistory: [],
    orderHistoryLoading: false,
    orderHistoryError: '',
    canRepeatHistory: true,
    repeatActionLoading: false,
};

const mountPanel = (props = {}) => mount(OrderPanel, {
    props: {
        ...baseProps,
        ...props,
    },
});

describe('OrderPanel history and repeat action', () => {
    it('renders submitted order history with repeat button', () => {
        const wrapper = mountPanel({
            orderHistory: [historyOrder],
        });

        expect(wrapper.find('[data-testid="order-history-section"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="order-repeat-button"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Soup #1');
    });

    it('shows compact empty history state when there are no submitted orders', () => {
        const wrapper = mountPanel({
            orderHistory: [],
        });

        expect(wrapper.find('[data-testid="order-history-section"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="order-history-empty-state"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="order-repeat-button"]').exists()).toBe(false);
    });

    it('emits repeat-order with selected history order', async () => {
        const wrapper = mountPanel({
            orderHistory: [historyOrder],
        });

        await wrapper.get('[data-testid="order-repeat-button"]').trigger('click');

        expect(wrapper.emitted('repeat-order')?.[0]).toEqual([historyOrder]);
    });

    it('disables repeat button and shows hint when current cycle is closed', () => {
        const wrapper = mountPanel({
            orderHistory: [historyOrder],
            canRepeatHistory: false,
        });

        const button = wrapper.get('[data-testid="order-repeat-button"]');

        expect(button.element.disabled).toBe(true);
        expect(wrapper.find('[data-testid="order-repeat-closed-hint"]').exists()).toBe(true);
    });

    it('disables repeat for history entries that cannot be repeated', () => {
        const wrapper = mountPanel({
            orderHistory: [{ ...historyOrder, can_repeat: false }],
            canRepeatHistory: true,
        });

        expect(wrapper.get('[data-testid="order-repeat-button"]').element.disabled).toBe(true);
    });
});
