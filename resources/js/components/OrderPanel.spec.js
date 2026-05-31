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
    statusDetail: '',
    disabledCheckoutLabel: '',
    disabledCheckoutHelper: '',
    emptyStateDetail: '',
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

    it('shows short cycle status and separate muted detail while keeping the empty cart centered', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            orderItems: [],
            statusLine: 'Приём открыт',
            statusDetail: 'До 05.06 в 07:00',
            canEditOrder: false,
            compactCart: true,
        });

        const emptyState = wrapper.get('[data-testid="order-panel-empty-state"]');
        const footer = wrapper.get('[data-testid="order-panel-footer"]');
        const status = wrapper.get('[data-testid="order-cycle-status"]');
        const detail = wrapper.get('[data-testid="order-cycle-status-detail"]');

        expect(wrapper.get('[data-testid="order-panel-heading"]').text()).toContain('Корзина');
        expect(status.text()).toBe('Приём открыт');
        expect(status.classes()).toContain('rounded-full');
        expect(detail.text()).toBe('До 05.06 в 07:00');
        expect(detail.classes()).toContain('text-slate-500');
        expect(wrapper.text()).toContain('Корзина пуста');
        expect(wrapper.text()).toContain('Добавьте блюда из каталога.');
        expect(emptyState.classes()).toContain('flex-1');
        expect(emptyState.classes()).toContain('justify-center');
        expect(footer.text()).toContain('Итого');
        expect(footer.text()).toContain('0 ₽');
        expect(footer.find('button').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('0 позиций');
        expect(wrapper.text()).not.toContain('Приём открыт · до');
        expect(wrapper.text()).not.toContain('Приём заказов закрыт');
    });

    it('supports upcoming and closed badge text without unclear standalone copy', () => {
        const upcoming = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            statusLine: 'Приём скоро',
            statusDetail: 'Откроется 01.06 в 00:00',
        });
        const closed = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            statusLine: 'Приём закрыт',
        });

        expect(upcoming.get('[data-testid="order-cycle-status"]').text()).toBe('Приём скоро');
        expect(upcoming.get('[data-testid="order-cycle-status-detail"]').text()).toBe('Откроется 01.06 в 00:00');
        expect(closed.get('[data-testid="order-cycle-status"]').text()).toBe('Приём закрыт');
        expect(closed.find('[data-testid="order-cycle-status-detail"]').exists()).toBe(false);
        expect(upcoming.text()).not.toMatch(/(^|\s)Скоро(\s|$)/);
        expect(upcoming.text()).not.toContain('Скоро откроется ·');
    });

    it('shows calm disabled checkout copy for upcoming and closed ordering states', () => {
        const commonOrder = {
            id: 15,
            status: 'draft',
            total_price: '250.00',
            items: [],
        };
        const commonItems = [
            {
                id: 77,
                menu_item_id: 11,
                title_snapshot: 'Суп с курицей',
                price_snapshot: '250.00',
                quantity: 1,
            },
        ];

        const upcoming = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: commonOrder,
            orderItems: commonItems,
            canEditOrder: false,
            disabledCheckoutLabel: 'Заказы откроются 01.06',
            disabledCheckoutHelper: 'Оформить заказ можно с 01.06 в 00:00.',
        });
        const closed = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: commonOrder,
            orderItems: commonItems,
            canEditOrder: false,
            disabledCheckoutLabel: 'Приём заказов закрыт',
            disabledCheckoutHelper: 'Новый цикл появится позже.',
        });

        const upcomingButton = upcoming.get('[data-testid="order-panel-disabled-checkout-button"]');
        const closedButton = closed.get('[data-testid="order-panel-disabled-checkout-button"]');

        expect(upcomingButton.text()).toContain('Заказы откроются 01.06');
        expect(upcomingButton.attributes('disabled')).toBeDefined();
        expect(upcoming.get('[data-testid="order-panel-disabled-checkout-helper"]').text()).toBe('Оформить заказ можно с 01.06 в 00:00.');
        expect(closedButton.text()).toContain('Приём заказов закрыт');
        expect(closedButton.attributes('disabled')).toBeDefined();
        expect(closed.get('[data-testid="order-panel-disabled-checkout-helper"]').text()).toBe('Новый цикл появится позже.');
    });

    it('keeps the empty cart centered while explaining upcoming ordering', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            orderItems: [],
            statusLine: 'Приём скоро',
            statusDetail: 'Откроется 01.06 в 00:00',
            emptyStateDetail: 'Оформить заказ можно с 01.06 в 00:00.',
        });

        const emptyState = wrapper.get('[data-testid="order-panel-empty-state"]');

        expect(emptyState.classes()).toContain('justify-center');
        expect(emptyState.text()).toContain('Корзина пуста');
        expect(emptyState.text()).toContain('Добавьте блюда из каталога.');
        expect(emptyState.get('[data-testid="order-panel-empty-state-detail"]').text()).toBe('Оформить заказ можно с 01.06 в 00:00.');
    });

    it('shows a real checkout CTA button instead of zero total for unauthenticated guests', async () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            isAuthenticated: false,
            order: null,
            orderItems: [],
            canEditOrder: false,
        });

        const footer = wrapper.get('[data-testid="order-panel-footer"]');
        const button = footer.get('[data-testid="order-panel-guest-checkout-button"]');

        expect(button.text()).toContain('Оформить заказ');
        expect(button.classes()).toContain('bg-blue-700');
        expect(button.classes()).toContain('h-[3.625rem]');
        expect(button.classes()).toContain('rounded-full');
        expect(button.classes()).toContain('font-bold');
        expect(footer.find('[data-testid="order-panel-guest-checkout-prompt"]').exists()).toBe(false);
        expect(footer.find('[data-testid="order-panel-total-label"]').exists()).toBe(false);
        expect(footer.find('[data-testid="order-panel-total-price"]').exists()).toBe(false);
        expect(footer.text()).not.toContain('Итого');
        expect(footer.text()).not.toContain('0 ₽');

        await button.trigger('click');

        expect(wrapper.emitted('open-auth')).toHaveLength(1);
    });

    it('renders cart rows in the same order received instead of alphabetical order', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: {
                id: 15,
                status: 'draft',
                total_price: '600.00',
                items: [],
            },
            orderItems: [
                {
                    id: 77,
                    menu_item_id: 11,
                    title_snapshot: 'Яблочное блюдо',
                    price_snapshot: '100.00',
                    quantity: 1,
                },
                {
                    id: 78,
                    menu_item_id: 12,
                    title_snapshot: 'Ананасовое блюдо',
                    price_snapshot: '200.00',
                    quantity: 1,
                },
                {
                    id: 79,
                    menu_item_id: 13,
                    title_snapshot: 'Борщ',
                    price_snapshot: '300.00',
                    quantity: 1,
                },
            ],
            menuItemsById: new Map([
                [11, { id: 11, title: 'Яблочное блюдо', weight: '300 г', image_url: null, image_display_url: null }],
                [12, { id: 12, title: 'Ананасовое блюдо', weight: '300 г', image_url: null, image_display_url: null }],
                [13, { id: 13, title: 'Борщ', weight: '300 г', image_url: null, image_display_url: null }],
            ]),
            totalPositions: 3,
            canEditOrder: true,
        });

        const titles = wrapper
            .findAll('[data-testid="order-panel-item-title"]')
            .map((title) => title.text());

        expect(titles).toEqual(['Яблочное блюдо', 'Ананасовое блюдо', 'Борщ']);
        expect(titles).not.toEqual(['Ананасовое блюдо', 'Борщ', 'Яблочное блюдо']);
    });

    it('keeps cart row order when the second item quantity changes', async () => {
        const orderItems = [
            {
                id: 77,
                menu_item_id: 11,
                title_snapshot: 'Яблочное блюдо',
                price_snapshot: '100.00',
                quantity: 1,
            },
            {
                id: 78,
                menu_item_id: 12,
                title_snapshot: 'Ананасовое блюдо',
                price_snapshot: '200.00',
                quantity: 1,
            },
            {
                id: 79,
                menu_item_id: 13,
                title_snapshot: 'Борщ',
                price_snapshot: '300.00',
                quantity: 1,
            },
        ];
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: {
                id: 15,
                status: 'draft',
                total_price: '600.00',
                items: [],
            },
            orderItems,
            menuItemsById: new Map([
                [11, { id: 11, title: 'Яблочное блюдо', weight: '300 г', image_url: null, image_display_url: null }],
                [12, { id: 12, title: 'Ананасовое блюдо', weight: '300 г', image_url: null, image_display_url: null }],
                [13, { id: 13, title: 'Борщ', weight: '300 г', image_url: null, image_display_url: null }],
            ]),
            totalPositions: 3,
            canEditOrder: true,
        });

        await wrapper.setProps({
            orderItems: [
                orderItems[0],
                { ...orderItems[1], quantity: 2 },
                orderItems[2],
            ],
            totalPositions: 4,
        });

        const titles = wrapper
            .findAll('[data-testid="order-panel-item-title"]')
            .map((title) => title.text());
        const quantities = wrapper
            .findAll('[data-testid="order-panel-item-stepper"]')
            .map((stepper) => stepper.text());

        expect(titles).toEqual(['Яблочное блюдо', 'Ананасовое блюдо', 'Борщ']);
        expect(quantities[1]).toBe('2');
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

    it('renders full-page order rows with cart-matched hierarchy and constrained spacing', () => {
        const wrapper = mountPanel({
            panelTitle: 'Мой заказ',
            order: {
                id: 15,
                status: 'draft',
                total_price: '100000.00',
                items: [],
            },
            orderItems: [
                {
                    id: 77,
                    menu_item_id: 11,
                    title_snapshot: 'Суп с курицей',
                    price_snapshot: '50000.00',
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
        const title = wrapper.get('[data-testid="order-panel-item-title"]');
        const weight = wrapper.get('[data-testid="order-panel-item-weight"]');
        const actions = wrapper.get('[data-testid="order-panel-item-actions"]');
        const stepper = wrapper.get('[data-testid="order-panel-item-stepper"]');
        const price = wrapper.get('[data-testid="order-panel-item-price"]');
        const remove = wrapper.get('[data-testid="order-panel-item-remove"]');
        const totalPrice = wrapper.get('[data-testid="order-panel-total-price"]');

        expect(wrapper.classes()).not.toContain('cart-panel-compact');
        expect(list.classes()).toContain('mx-auto');
        expect(list.classes()).toContain('max-w-[46rem]');
        expect(list.classes()).toContain('space-y-2');
        expect(list.classes()).not.toContain('divide-y');
        expect(list.classes()).not.toContain('divide-slate-100');
        expect(item.classes().join(' ')).toContain('grid-cols-[4.75rem_minmax(0,1fr)]');
        expect(item.classes().join(' ')).not.toContain('ring-1');
        expect(imageWrap.classes().join(' ')).toContain('bg-slate-50');
        expect(title.classes()).toContain('text-[#595959]');
        expect(title.classes()).toContain('font-semibold');
        expect(weight.classes()).toContain('text-[#a6a6a6]');
        expect(weight.classes()).toContain('font-semibold');
        expect(actions.classes().join(' ')).toContain('grid-cols-[auto_minmax(0,1fr)_auto_auto]');
        expect(stepper.classes()).toContain('h-8');
        expect(stepper.classes()).toContain('bg-blue-700');
        expect(price.text()).toBe('100000 ₽');
        expect(price.classes()).toContain('text-[#404040]');
        expect(remove.classes()).toContain('justify-self-end');
        expect(totalPrice.text()).toBe('100000 ₽');
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
        const stepperButtons = stepper.findAll('button');
        const stepperCount = stepper.find('span');

        expect(wrapper.classes()).toContain('cart-panel-compact');
        expect(list.classes()).not.toContain('divide-y');
        expect(list.classes()).not.toContain('border-b');
        expect(list.classes()).toContain('pb-[9rem]');
        expect(list.classes().join(' ')).not.toContain('after:border');
        expect(itemClasses).toContain('grid-cols-[5.1875rem_minmax(0,1fr)]');
        expect(itemClasses).toContain('min-h-[6.625rem]');
        expect(itemClasses).toContain('py-2.5');
        expect(itemClasses).not.toContain('ring-1');
        expect(itemClasses).not.toContain('border');
        expect(itemClasses).not.toContain('rounded-2xl');
        expect(imageWrapClasses).toContain('size-[5.1875rem]');
        expect(imageWrapClasses).toContain('bg-slate-50');
        expect(imageWrapClasses).not.toContain('ring');
        expect(imageWrapClasses).not.toContain('bg-white');
        expect(imageWrapClasses).not.toContain('blue');
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
        expect(image.classes().join(' ')).not.toContain('blue');
        expect(title.text()).toContain('Суп с курицей');
        expect(title.classes()).toContain('text-[14px]');
        expect(title.classes()).toContain('leading-4');
        expect(title.classes()).toContain('font-semibold');
        expect(title.classes()).toContain('tracking-normal');
        expect(title.classes()).toContain('text-[#595959]');
        expect(weight.text()).toContain('300 г');
        expect(weight.classes()).toContain('mt-px');
        expect(weight.classes()).toContain('text-[13px]');
        expect(weight.classes()).toContain('font-semibold');
        expect(weight.classes()).toContain('leading-[15px]');
        expect(weight.classes()).toContain('text-[#a6a6a6]');
        expect(actions.classes()).toContain('grid-cols-[auto_1fr_auto]');
        expect(actions.classes()).toContain('items-center');
        expect(stepper.text()).toContain('2');
        expect(stepperButtons).toHaveLength(2);
        expect(stepperClasses).toContain('h-7');
        expect(stepperClasses).toContain('w-[5.375rem]');
        expect(stepperClasses).toContain('grid-cols-[1.625rem_2.125rem_1.625rem]');
        expect(stepperClasses).toContain('bg-blue-700');
        expect(stepperClasses).not.toContain('border');
        expect(stepperClasses).not.toContain('bg-white');
        expect(stepperClasses).not.toContain('bg-slate-100');
        expect(stepperButtons[0].classes()).toContain('size-6');
        expect(stepperButtons[0].classes()).toContain('justify-self-start');
        expect(stepperButtons[0].classes()).toContain('text-white/85');
        expect(stepperButtons[0].find('svg').classes()).toContain('size-4');
        expect(stepperButtons[1].classes()).toContain('size-6');
        expect(stepperButtons[1].classes()).toContain('justify-self-end');
        expect(stepperButtons[1].classes()).toContain('text-white');
        expect(stepperButtons[1].find('svg').classes()).toContain('size-4');
        expect(stepperCount.classes()).toContain('justify-self-center');
        expect(stepperCount.classes()).toContain('text-[14px]');
        expect(stepperCount.classes()).toContain('text-white');
        expect(stepperCount.classes()).toContain('font-semibold');
        expect(price.text()).toContain('500');
        expect(price.classes()).toContain('col-start-3');
        expect(price.classes()).toContain('justify-self-end');
        expect(price.classes()).toContain('text-[16px]');
        expect(price.classes()).toContain('font-semibold');
        expect(price.classes()).toContain('leading-[18px]');
        expect(price.classes()).toContain('text-[#404040]');
        expect(footer.classes()).not.toContain('border-t');
        expect(footer.classes()).toContain('cart-panel-footer-overlay');
        expect(footer.classes()).toContain('-mt-7');
        expect(totalLabel.classes()).toContain('text-center');
        expect(totalLabel.classes()).toContain('text-[12px]');
        expect(totalLabel.classes()).toContain('font-semibold');
        expect(totalLabel.classes()).toContain('text-[#a0a0a0]');
        expect(totalPrice.classes()).toContain('block');
        expect(totalPrice.classes()).toContain('text-center');
        expect(totalPrice.classes()).toContain('text-[32px]');
        expect(totalPrice.classes()).toContain('leading-[36px]');
        expect(totalPrice.classes()).toContain('text-[#404040]');
        expect(checkoutButton.classes()).toContain('h-[3.625rem]');
        expect(checkoutButton.classes()).toContain('rounded-full');
        expect(checkoutButton.classes()).toContain('bg-blue-700');
    });

    it('uses active blue readonly quantity pill for selected cart items', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: {
                id: 15,
                status: 'submitted',
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
            canEditOrder: false,
        });

        const quantityPill = wrapper.get('[data-testid="order-panel-item-stepper"]');

        expect(quantityPill.text()).toBe('1 шт.');
        expect(quantityPill.classes()).toContain('h-7');
        expect(quantityPill.classes()).toContain('bg-blue-700');
        expect(quantityPill.classes()).toContain('text-white');
        expect(quantityPill.findAll('button')).toHaveLength(0);
    });

    it('formats compact cart prices as continuous integers without thousands separators', () => {
        const wrapper = mountPanel({
            panelTitle: 'Корзина',
            compactCart: true,
            order: {
                id: 15,
                status: 'draft',
                total_price: '1909.00',
                items: [],
            },
            orderItems: [
                {
                    id: 77,
                    menu_item_id: 11,
                    title_snapshot: 'Суп с курицей',
                    price_snapshot: '508.00',
                    quantity: 2,
                },
            ],
            menuItemsById: new Map([
                [11, { id: 11, title: 'Суп с курицей', weight: '300 г', image_url: null, image_display_url: null }],
            ]),
            totalPositions: 2,
            canEditOrder: true,
        });

        const itemPrice = wrapper.get('[data-testid="order-panel-item-price"]');
        const totalPrice = wrapper.get('[data-testid="order-panel-total-price"]');

        expect(itemPrice.text()).toBe('1016 ₽');
        expect(totalPrice.text()).toBe('1909 ₽');
        expect(wrapper.text()).not.toContain('1 016 ₽');
        expect(wrapper.text()).not.toContain('1 909 ₽');
    });

    it('does not render order history or repeat copy in cart panel', () => {
        const wrapper = mountPanel({
            order: {
                id: 15,
                status: 'draft',
                total_price: '0.00',
                items: [],
            },
            canEditOrder: false,
        });

        expect(wrapper.text()).not.toContain('История заказов');
        expect(wrapper.text()).not.toContain('Уже заказывали');
        expect(wrapper.text()).not.toContain('Повторить заказ');
        expect(wrapper.text()).not.toContain('Приём заказов закрыт');
        expect(wrapper.text().toLowerCase()).not.toContain('черновик');
    });
});
