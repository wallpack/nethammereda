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
    compactCart: false,
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

    it('renders compact desktop cart item layout with unified list styling', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
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

        const list = wrapper.get('[data-testid="order-panel-items-scroll"]');
        const item = wrapper.get('[data-testid="order-panel-item"]');
        const imageWrap = wrapper.get('[data-testid="order-panel-item-image-wrap"]');
        const image = wrapper.get('[data-testid="order-panel-item-image"]');
        const title = wrapper.get('[data-testid="order-panel-item-title"]');
        const weight = wrapper.get('[data-testid="order-panel-item-weight"]');
        const actions = wrapper.get('[data-testid="order-panel-item-actions"]');
        const stepper = wrapper.get('[data-testid="order-panel-item-stepper"]');
        const price = wrapper.get('[data-testid="order-panel-item-price"]');
        const footer = wrapper.get('[data-testid="order-panel-footer"]');
        const totalLabel = footer.get('[data-testid="order-panel-total-label"]');
        const totalPrice = footer.get('[data-testid="order-panel-total-price"]');
        const checkoutButton = footer.get('button');

        const itemClasses = item.classes().join(' ');
        const imageWrapClasses = imageWrap.classes().join(' ');
        const stepperClasses = stepper.classes().join(' ');

        expect(wrapper.classes()).toContain('cart-panel-compact');
        expect(list.classes()).toContain('divide-y');
        expect(itemClasses).toContain('grid-cols-[5.1875rem_minmax(0,1fr)]');
        expect(itemClasses).toContain('min-h-[6.625rem]');
        expect(itemClasses).toContain('py-3');
        expect(itemClasses).not.toContain('ring-1');
        expect(itemClasses).not.toContain('border');
        expect(itemClasses).not.toContain('rounded-2xl');
        expect(imageWrapClasses).toContain('size-[5.1875rem]');
        expect(imageWrapClasses).toContain('bg-slate-50');
        expect(imageWrapClasses).not.toContain('bg-white');
        expect(imageWrapClasses).not.toContain('blue');
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
        expect(image.classes().join(' ')).not.toContain('blue');
        expect(title.text()).toContain('Суп с курицей');
        expect(title.classes()).toContain('text-[13px]');
        expect(title.classes()).toContain('leading-4');
        expect(title.classes()).toContain('font-[650]');
        expect(title.classes()).toContain('text-[#565656]');
        expect(weight.text()).toContain('300 г');
        expect(weight.classes()).toContain('mt-0.5');
        expect(weight.classes()).toContain('text-[12.5px]');
        expect(weight.classes()).toContain('leading-4');
        expect(weight.classes()).toContain('text-[#8a8f98]');
        expect(actions.classes()).toContain('grid-cols-[auto_1fr_auto]');
        expect(actions.classes()).toContain('items-center');
        expect(stepper.text()).toContain('2');
        expect(stepper.findAll('button')).toHaveLength(2);
        expect(stepperClasses).toContain('h-[1.875rem]');
        expect(stepperClasses).toContain('w-[5.5rem]');
        expect(stepperClasses).toContain('bg-slate-100');
        expect(stepperClasses).not.toContain('border');
        expect(stepperClasses).not.toContain('bg-white');
        expect(price.text()).toContain('500');
        expect(price.classes()).toContain('col-start-3');
        expect(price.classes()).toContain('justify-self-end');
        expect(price.classes()).toContain('text-[14px]');
        expect(price.classes()).toContain('leading-[1.875rem]');
        expect(price.classes()).toContain('text-[#464646]');
        expect(totalLabel.classes()).toContain('text-[12px]');
        expect(totalPrice.classes()).toContain('text-[23px]');
        expect(checkoutButton.classes()).toContain('h-[3.625rem]');
        expect(checkoutButton.classes()).toContain('rounded-[1.25rem]');
        expect(checkoutButton.classes()).toContain('bg-blue-700');
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
