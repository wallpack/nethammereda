import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HistoryPanel from './HistoryPanel.vue';

const historyItem = {
    id: 77,
    title_snapshot: 'Курица терияки с рисом',
    status: 'eaten',
    eaten_at: '2026-05-30T09:20:00.000000Z',
};

const mountPanel = (props = {}) => mount(HistoryPanel, {
    props: {
        fridgeHistory: [historyItem],
        fridgeLoading: false,
        showHeading: true,
        orderSkeletonRows: [1, 2],
        ...props,
    },
});

describe('HistoryPanel UI', () => {
    it('renders readable rows with status chip and time', () => {
        const wrapper = mountPanel();

        expect(wrapper.find('[data-testid="history-panel-row"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="history-panel-status-chip"]').text()).toContain('Съедено');
        expect(wrapper.find('[data-testid="history-panel-time"]').text()).not.toBe('—');
    });

    it('shows empty state when history is empty', () => {
        const wrapper = mountPanel({
            fridgeHistory: [],
        });

        expect(wrapper.text()).toContain('Истории пока нет.');
    });
});
