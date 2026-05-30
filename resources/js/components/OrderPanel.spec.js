import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrderPanel from './OrderPanel.vue';

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
};

const mountPanel = (props = {}) => mount(OrderPanel, {
    props: {
        ...baseProps,
        ...props,
    },
});

describe('OrderPanel cart-only UX', () => {
    it('does not render order history block inside cart panel', () => {
        const wrapper = mountPanel();

        expect(wrapper.find('[data-testid="order-history-section"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('История заказов');
        expect(wrapper.text()).not.toContain('Уже заказывали');
        expect(wrapper.text()).not.toContain('Повторить заказ');
        expect(wrapper.find('[data-testid="order-repeat-button"]').exists()).toBe(false);
    });

    it('shows empty current-cart state', () => {
        const wrapper = mountPanel({
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            orderItems: [],
        });

        expect(wrapper.text()).toContain('Корзина пуста');
        expect(wrapper.text()).toContain('Добавьте блюда из каталога.');
    });

    it('renders internal scroll and sticky footer structure for current cart', () => {
        const wrapper = mountPanel({
            order: {
                id: 15,
                status: 'draft',
                total_price: '250.00',
                items: [],
            },
            orderItems: [
                {
                    id: 77,
                    menu_item_id: 11,
                    title_snapshot: 'Суп с курицей',
                    price_snapshot: '250.00',
                    quantity: 1,
                },
            ],
            menuItemsById: new Map([
                [11, { id: 11, title: 'Суп с курицей', weight: '300 г', image_url: null, image_display_url: null }],
            ]),
            totalPositions: 1,
            canEditOrder: true,
        });

        const list = wrapper.find('[data-testid="order-panel-items-scroll"]');
        const footer = wrapper.find('[data-testid="order-panel-footer"]');

        expect(list.exists()).toBe(true);
        expect(list.classes()).toContain('min-h-0');
        expect(list.classes()).toContain('overflow-y-auto');
        expect(footer.exists()).toBe(true);
        expect(footer.classes()).toContain('safe-cart-footer');
        expect(footer.classes()).toContain('sticky');
    });

    it('uses compact cart status copy and never exposes draft wording', () => {
        const wrapper = mountPanel({
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            statusLine: 'Приём заказов закрыт',
            canEditOrder: false,
        });

        expect(wrapper.text()).toContain('Приём заказов закрыт');
        expect(wrapper.text().toLowerCase()).not.toContain('черновик');
    });
});
