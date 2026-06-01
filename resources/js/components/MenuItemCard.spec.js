import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MenuItemCard from './MenuItemCard.vue';

const baseItem = {
    id: 11,
    title: 'Суп с курицей',
    category: { id: 1, name: 'Супы' },
    weight: '300 г',
    calories: 220,
    price: '250.00',
    is_active: true,
    image_url: null,
    image_display_url: null,
};

const mountCard = (props = {}) => mount(MenuItemCard, {
    props: {
        item: baseItem,
        orderItem: null,
        isFavorite: false,
        isAuthenticated: true,
        canEditOrder: true,
        disabledReason: '',
        actionLoading: false,
        ...props,
    },
});

describe('MenuItemCard UI', () => {
    it('renders title, price, placeholder and a price stepper control', () => {
        const wrapper = mountCard();

        const stepper = wrapper.find('[data-testid="menu-item-price-stepper"]');
        const price = wrapper.find('[data-testid="menu-item-stepper-price"]');
        const buttons = stepper.findAll('button');

        expect(stepper.classes()).toContain('bg-blue-50');
        expect(stepper.classes()).toContain('border-transparent');
        expect(stepper.classes()).toContain('grid');
        expect(stepper.classes()).toContain('h-9');
        expect(stepper.classes()).toContain('w-[132px]');
        expect(stepper.classes()).toContain('grid-cols-[2.25rem_minmax(0,1fr)_2.25rem]');
        expect(stepper.classes()).not.toContain('w-full');
        expect(stepper.classes()).toContain('text-[#404040]');
        expect(price.classes()).toContain('text-[#404040]');
        expect(price.classes()).toContain('text-[13px]');
        expect(price.classes()).not.toContain('bg-white/95');
        expect(price.classes()).not.toContain('rounded-full');
        expect(price.classes()).not.toContain('px-1.5');
        expect(buttons[0].find('svg').classes()).toContain('size-5');
        expect(buttons[1].find('svg').classes()).toContain('size-5');
        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('300 г');
        expect(wrapper.text()).toContain('250');
        expect(wrapper.text()).toContain('Фото скоро');
        expect(wrapper.text()).not.toContain('Супы · 300 г');
        expect(wrapper.text()).not.toContain('220 ккал');
        expect(stepper.exists()).toBe(true);
        expect(buttons).toHaveLength(2);
        expect(buttons[0].attributes('aria-label')).toContain('Уменьшить количество');
        expect(buttons[1].attributes('aria-label')).toContain('Добавить в заказ');
        expect(wrapper.text()).not.toContain('Добавить');
    });

    it('renders image on a clean white image area without dashboard tint', () => {
        const wrapper = mountCard({
            item: {
                ...baseItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            },
        });

        const card = wrapper.find('[data-testid="menu-item-card"]');
        const imageArea = wrapper.find('[data-testid="menu-item-image-area"]');
        const image = wrapper.find(`img[alt="${baseItem.title}"]`);

        expect(card.classes()).toContain('min-w-0');
        expect(card.classes()).toContain('border-transparent');
        expect(card.classes()).toContain('shadow-none');
        expect(card.classes().join(' ')).not.toContain('hover:border');
        expect(imageArea.classes()).toContain('size-[176px]');
        expect(imageArea.classes()).toContain('bg-white');
        expect(imageArea.classes()).not.toContain('bg-slate-50');
        expect(image.exists()).toBe(true);
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
        expect(image.attributes('loading')).toBe('lazy');
        expect(image.attributes('decoding')).toBe('async');
        expect(image.attributes('width')).toBe('900');
        expect(image.attributes('height')).toBe('900');
    });

    it('prioritizes only the first likely LCP image', () => {
        const priority = mountCard({
            item: {
                ...baseItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            },
            itemIndex: 0,
        });
        const belowFold = mountCard({
            item: {
                ...baseItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            },
            itemIndex: 8,
        });

        const priorityImage = priority.find(`img[alt="${baseItem.title}"]`);
        const belowFoldImage = belowFold.find(`img[alt="${baseItem.title}"]`);

        expect(priorityImage.attributes('loading')).toBe('eager');
        expect(priorityImage.attributes('fetchpriority')).toBe('high');
        expect(belowFoldImage.attributes('loading')).toBe('lazy');
        expect(belowFoldImage.attributes('fetchpriority')).toBeUndefined();
    });

    it('uses cart-matched title and meta typography in strict image-title-meta-control order', () => {
        const wrapper = mountCard();
        const imageArea = wrapper.find('[data-testid="menu-item-image-area"]');
        const copyColumn = wrapper.find('[data-testid="menu-item-copy-column"]');
        const title = wrapper.find('h3');
        const meta = wrapper.find('[data-testid="menu-item-meta"]');
        const stepper = wrapper.find('[data-testid="menu-item-price-stepper"]');

        expect(imageArea.element.compareDocumentPosition(title.element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(title.element.compareDocumentPosition(meta.element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(meta.element.compareDocumentPosition(stepper.element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(copyColumn.classes()).toContain('w-[176px]');
        expect(copyColumn.classes()).toContain('max-w-full');
        expect(title.classes()).toContain('text-[13.25px]');
        expect(title.classes()).toContain('leading-[16px]');
        expect(title.classes()).toContain('font-semibold');
        expect(title.classes()).toContain('text-[#595959]');
        expect(title.classes()).toContain('line-clamp-2');
        expect(meta.classes()).toContain('mt-1');
        expect(meta.classes()).toContain('text-[13px]');
        expect(meta.classes()).toContain('leading-[15px]');
        expect(meta.classes()).toContain('font-semibold');
        expect(meta.classes()).toContain('text-[#737373]');
        expect(meta.text()).toBe('300 г');
    });

    it('displays cleaned catalog title and API display metadata', () => {
        const wrapper = mountCard({
            item: {
                ...baseItem,
                title: 'Запеканка картофельнаяс куриным жульеном',
                weight: null,
                display_weight: '250гр',
            },
        });
        const title = wrapper.find('h3');
        const meta = wrapper.find('[data-testid="menu-item-meta"]');

        expect(title.text()).toBe('Запеканка картофельная с куриным жульеном');
        expect(title.attributes('title')).toBe('Запеканка картофельная с куриным жульеном');
        expect(meta.text()).toBe('250 г');
    });

    it('does not render closed text and shows a muted disabled price stepper when ordering is closed', () => {
        const wrapper = mountCard({
            canEditOrder: false,
        });
        const stepper = wrapper.find('[data-testid="menu-item-price-stepper"]');
        const buttons = stepper.findAll('button');

        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('250');
        expect(wrapper.text()).not.toContain('Приём закрыт');
        expect(stepper.classes()).toContain('bg-blue-50/60');
        expect(stepper.classes()).toContain('text-[#404040]/60');
        expect(buttons).toHaveLength(2);
        expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('renders active blue control and quantity overlay when item is already selected', () => {
        const wrapper = mountCard({
            orderItem: {
                id: 77,
                menu_item_id: baseItem.id,
                quantity: 2,
                price_snapshot: baseItem.price,
                title_snapshot: baseItem.title,
            },
        });

        const stepper = wrapper.find('[data-testid="menu-item-price-stepper"]');
        const overlay = wrapper.find('[data-testid="menu-item-quantity-overlay"]');

        const price = wrapper.find('[data-testid="menu-item-stepper-price"]');
        const buttons = stepper.findAll('button');

        expect(stepper.classes()).toContain('bg-blue-700');
        expect(stepper.classes()).toContain('h-9');
        expect(stepper.classes()).toContain('w-[132px]');
        expect(stepper.text()).toContain('250');
        expect(price.classes()).toContain('text-white');
        expect(price.classes()).not.toContain('text-[#404040]');
        expect(price.classes()).not.toContain('bg-white/95');
        expect(price.classes()).not.toContain('rounded-full');
        expect(buttons[0].classes()).toContain('text-white');
        expect(buttons[1].classes()).toContain('text-white');
        expect(buttons[0].find('svg').classes()).toContain('size-5');
        expect(buttons[1].find('svg').classes()).toContain('size-5');
        expect(overlay.exists()).toBe(true);
        expect(overlay.text()).toContain('2');
    });

    it('keeps selected disabled controls in the active blue state', () => {
        const wrapper = mountCard({
            canEditOrder: false,
            orderItem: {
                id: 77,
                menu_item_id: baseItem.id,
                quantity: 1,
                price_snapshot: baseItem.price,
                title_snapshot: baseItem.title,
            },
        });

        const stepper = wrapper.find('[data-testid="menu-item-price-stepper"]');
        const price = wrapper.find('[data-testid="menu-item-stepper-price"]');
        const buttons = stepper.findAll('button');

        expect(stepper.classes()).toContain('bg-blue-700');
        expect(price.classes()).toContain('text-white');
        expect(price.classes()).not.toContain('text-[#404040]');
        expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('formats catalog stepper prices without thousands separators', () => {
        const prices = ['100.00', '1000.00', '10000.00', '100000.00'];
        const rendered = prices.map((price) => mountCard({ item: { ...baseItem, price } })
            .find('[data-testid="menu-item-stepper-price"]')
            .text());

        expect(rendered).toEqual(['100 ₽', '1000 ₽', '10000 ₽', '100000 ₽']);
    });

    it('does not render quantity overlay when quantity is zero', () => {
        const wrapper = mountCard();

        expect(wrapper.find('[data-testid="menu-item-quantity-overlay"]').exists()).toBe(false);
    });

    it('keeps favorite button visible', () => {
        const wrapper = mountCard({
            isFavorite: true,
        });

        const favoriteButton = wrapper.find('button[aria-pressed="true"]');

        expect(favoriteButton.exists()).toBe(true);
    });
});
