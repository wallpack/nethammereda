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

    it('shows centered empty current-cart state without closed/status service copy', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            orderItems: [],
            statusLine: 'Приём заказов закрыт',
            canEditOrder: false,
        });

        const emptyState = wrapper.get('[data-testid="order-panel-empty-state"]');
        const footer = wrapper.get('[data-testid="order-panel-footer"]');

        expect(wrapper.text()).toContain('Корзина');
        expect(wrapper.text()).toContain('Корзина пуста');
        expect(wrapper.text()).toContain('Добавьте блюда из каталога.');
        expect(emptyState.classes()).toContain('flex-1');
        expect(emptyState.classes()).toContain('justify-center');
        expect(footer.text()).toContain('Итого');
        expect(footer.text()).toContain('0 ₽');
        expect(footer.find('button').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('0 позиций');
        expect(wrapper.text()).not.toContain('закрыта');
        expect(wrapper.text()).not.toContain('Закрыта');
        expect(wrapper.text()).not.toContain('Приём заказов закрыт');
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
        expect(list.classes()).toContain('scrollbar-none');
        expect(footer.exists()).toBe(true);
        expect(footer.classes()).toContain('safe-cart-footer');
        expect(footer.classes()).toContain('sticky');
    });

    it('renders food-delivery cart item layout with image, title, weight, stepper and right price', () => {
        const wrapper = mountPanel({
            order: {
                id: 15,
                status: 'draft',
                total_price: '500.00',
                items: [],
            },
            orderItems: [
                {
                    id: 77,
                    menu_item_id: 11,
                    title_snapshot: 'Суп с курицей',
                    price_snapshot: '250.00',
                    quantity: 2,
                },
            ],
            menuItemsById: new Map([
                [11, { id: 11, title: 'Суп с курицей', weight: '300 г', image_url: null, image_display_url: '/storage/menu-items/manual/11/soup.png' }],
            ]),
            totalPositions: 2,
            canEditOrder: true,
        });

        const item = wrapper.get('[data-testid="order-panel-item"]');
        const imageWrap = wrapper.get('[data-testid="order-panel-item-image-wrap"]');
        const image = wrapper.get('[data-testid="order-panel-item-image"]');
        const title = wrapper.get('[data-testid="order-panel-item-title"]');
        const weight = wrapper.get('[data-testid="order-panel-item-weight"]');
        const stepper = wrapper.get('[data-testid="order-panel-item-stepper"]');
        const price = wrapper.get('[data-testid="order-panel-item-price"]');

        expect(item.classes()).toContain('grid-cols-[4rem_minmax(0,1fr)]');
        expect(imageWrap.classes()).toContain('bg-white');
        expect(imageWrap.classes().join(' ')).not.toContain('blue');
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
        expect(image.classes().join(' ')).not.toContain('blue');
        expect(title.text()).toContain('Суп с курицей');
        expect(weight.text()).toContain('300 г');
        expect(stepper.text()).toContain('2');
        expect(stepper.findAll('button')).toHaveLength(2);
        expect(price.text()).toContain('500');
        expect(price.classes()).toContain('ml-auto');
    });

    it('does not render order history or repeat copy in cart panel', () => {
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

        expect(wrapper.text()).not.toContain('История заказов');
        expect(wrapper.text()).not.toContain('Уже заказывали');
        expect(wrapper.text()).not.toContain('Повторить заказ');
        expect(wrapper.text()).not.toContain('Приём заказов закрыт');
        expect(wrapper.text().toLowerCase()).not.toContain('черновик');
    });
});
