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
        expect(wrapper.text()).toContain('Фото скоро');
        expect(wrapper.find('[data-testid="menu-item-add-button"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Добавить');
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

    it('keeps card visible in closed state and shows a compact muted CTA badge', () => {
        const wrapper = mountCard({
            canEditOrder: false,
        });
        const closedBadge = wrapper.find('[data-slot="badge"]');

        expect(wrapper.find('[data-testid="menu-item-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(baseItem.title);
        expect(wrapper.text()).toContain('Приём закрыт');
        expect(closedBadge.classes()).toContain('h-9');
        expect(closedBadge.classes()).toContain('bg-slate-50');
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
