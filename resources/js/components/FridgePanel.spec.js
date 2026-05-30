import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FridgePanel from './FridgePanel.vue';

const fridgeItem = {
    id: 31,
    title_snapshot: 'Котлета с пюре',
    quantity_remaining: 2,
    status: 'in_fridge',
    expires_at: '2026-05-30T11:30:00.000000Z',
    image_display_url: null,
    image_url: null,
};

const dialogStubs = {
    AlertDialogRoot: { template: '<div><slot /></div>' },
    AlertDialogTrigger: { template: '<div><slot /></div>' },
    AlertDialogPortal: { template: '<div><slot /></div>' },
    AlertDialogOverlay: { template: '<div><slot /></div>' },
    AlertDialogContent: { template: '<div><slot /></div>' },
    AlertDialogTitle: { template: '<div><slot /></div>' },
    AlertDialogCancel: { template: '<div><slot /></div>' },
    AlertDialogAction: { template: '<div><slot /></div>' },
};

const mountPanel = (props = {}) => mount(FridgePanel, {
    props: {
        fridgeItems: [fridgeItem],
        fridgeLoading: false,
        actionLoading: false,
        error: '',
        activeFridgeItemsCount: 1,
        fridgeMeta: {
            active_count: 1,
            total_portions: 2,
            expiring_soon_count: 0,
        },
        showHeading: true,
        orderSkeletonRows: [1, 2],
        ...props,
    },
    global: {
        stubs: dialogStubs,
    },
});

describe('FridgePanel UI', () => {
    it('renders compact fridge actions', () => {
        const wrapper = mountPanel();

        expect(wrapper.text()).toContain('Съел');
        expect(wrapper.text()).toContain('Съел всё');
        expect(wrapper.text()).toContain('Списать');
        expect(wrapper.text()).toContain('В холодильнике');
        expect(wrapper.text()).toContain('Порций');
        expect(wrapper.find('[data-testid="fridge-panel-scroll"]').exists()).toBe(true);

        const eatButton = wrapper.get('[data-testid="fridge-eat-one-button"]');
        const eatAllButton = wrapper.get('[data-testid="fridge-eat-all-button"]');
        const discardButton = wrapper.get('[data-testid="fridge-discard-button"]');
        expect(eatButton.classes().join(' ')).toContain('customer-cta');
        expect(eatButton.classes().join(' ')).toContain('h-11');
        expect(eatAllButton.classes().join(' ')).toContain('customer-secondary-action');
        expect(eatAllButton.classes().join(' ')).toContain('border-blue-200');
        expect(discardButton.classes().join(' ')).toContain('customer-tertiary-action');
    });

    it('renders fridge stats and cards with shared customer hierarchy', () => {
        const wrapper = mountPanel();

        const heading = wrapper.get('h2');
        const summaryValue = wrapper.get('[data-testid="fridge-summary-value"]');
        const card = wrapper.get('[data-testid="fridge-item-card"]');
        const title = wrapper.get('[data-testid="fridge-card-title"]');
        const status = wrapper.get('[data-testid="fridge-card-status"]');
        const expiry = wrapper.get('[data-testid="fridge-card-expiry"]');
        const quantity = wrapper.get('[data-testid="fridge-quantity-badge"]');
        const actions = wrapper.get('[data-testid="fridge-card-actions"]');

        expect(heading.classes()).toContain('customer-heading');
        expect(summaryValue.classes()).toContain('customer-stat-number');
        expect(card.classes()).toContain('customer-row-card');
        expect(title.classes()).toContain('customer-title');
        expect(status.classes()).toContain('customer-meta');
        expect(expiry.classes()).toContain('customer-meta');
        expect(quantity.classes()).toContain('customer-badge');
        expect(actions.classes()).toContain('grid');
        expect(actions.classes().join(' ')).toContain('sm:grid-cols-[minmax(7.5rem,1fr)_auto_auto]');
    });

    it('renders a real dish thumbnail when the fridge payload includes an image URL', () => {
        const wrapper = mountPanel({
            fridgeItems: [{
                ...fridgeItem,
                image_display_url: '/storage/menu-items/manual/31/cutlet.png',
            }],
        });

        const imageWrap = wrapper.get('[data-testid="fridge-card-image-wrap"]');
        const image = wrapper.get('[data-testid="fridge-card-image"]');

        expect(imageWrap.classes()).toContain('size-16');
        expect(image.attributes('src')).toBe('/storage/menu-items/manual/31/cutlet.png');
        expect(image.attributes('alt')).toBe('Котлета с пюре');
        expect(image.classes()).toContain('object-contain');
    });

    it('shows a neutral thumbnail placeholder when no dish image is available', () => {
        const wrapper = mountPanel();

        const imageWrap = wrapper.get('[data-testid="fridge-card-image-wrap"]');
        const placeholder = wrapper.get('[data-testid="fridge-card-image-placeholder"]');

        expect(imageWrap.classes()).toContain('size-16');
        expect(placeholder.exists()).toBe(true);
        expect(wrapper.find('[data-testid="fridge-card-image"]').exists()).toBe(false);
    });

    it('shows empty state when fridge is empty', () => {
        const wrapper = mountPanel({
            fridgeItems: [],
            activeFridgeItemsCount: 0,
        });

        expect(wrapper.text()).toContain('В вашем холодильнике пока ничего нет.');
    });
});
