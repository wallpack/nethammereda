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
        const buttons = stepper.findAll('button');

        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('250');
        expect(wrapper.text()).toContain('Фото скоро');
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
        expect(imageArea.classes()).toContain('h-[11rem]');
        expect(imageArea.classes()).toContain('bg-white');
        expect(imageArea.classes()).not.toContain('bg-slate-50');
        expect(image.exists()).toBe(true);
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
    });

    it('uses compact desktop title typography after shell scale-up', () => {
        const wrapper = mountCard();
        const title = wrapper.find('h3');

        expect(title.classes()).toContain('text-[0.9rem]');
        expect(title.classes()).toContain('sm:text-[0.94rem]');
        expect(title.classes()).toContain('line-clamp-2');
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
        expect(stepper.classes()).toContain('text-blue-300');
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

        expect(stepper.classes()).toContain('bg-blue-700');
        expect(stepper.text()).toContain('250');
        expect(overlay.exists()).toBe(true);
        expect(overlay.text()).toContain('2');
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
