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
    it('renders title, price, placeholder and add CTA', () => {
        const wrapper = mountCard();

        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('250');
        expect(wrapper.text()).toContain('Фото блюда появится скоро');
        expect(wrapper.find('[data-testid="menu-item-add-button"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Добавить');
    });

    it('renders image when image_display_url is available', () => {
        const wrapper = mountCard({
            item: {
                ...baseItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            },
        });

        const image = wrapper.find(`img[alt="${baseItem.title}"]`);

        expect(image.exists()).toBe(true);
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/11/soup.png');
    });

    it('keeps card visible in closed state and shows disabled CTA badge', () => {
        const wrapper = mountCard({
            canEditOrder: false,
        });

        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('Приём закрыт');
        expect(wrapper.find('[data-testid="menu-item-add-button"]').exists()).toBe(false);
    });

    it('keeps favorite button visible', () => {
        const wrapper = mountCard({
            isFavorite: true,
        });

        const favoriteButton = wrapper.find('button[aria-pressed="true"]');

        expect(favoriteButton.exists()).toBe(true);
    });
});
