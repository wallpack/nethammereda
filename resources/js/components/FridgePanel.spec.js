import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FridgePanel from './FridgePanel.vue';

const fridgeItem = {
    id: 31,
    title_snapshot: 'Котлета с пюре',
    quantity_remaining: 2,
    status: 'in_fridge',
    expires_at: '2026-05-30T11:30:00.000000Z',
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

        const eatButton = wrapper.findAll('button').find((button) => button.text().includes('Съел'));
        expect(eatButton?.classes().join(' ')).toContain('sm:w-auto');
    });

    it('shows empty state when fridge is empty', () => {
        const wrapper = mountPanel({
            fridgeItems: [],
            activeFridgeItemsCount: 0,
        });

        expect(wrapper.text()).toContain('В вашем холодильнике пока ничего нет.');
    });
});
