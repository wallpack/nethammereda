import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CategorySidebar from './CategorySidebar.vue';

const expectClasses = (element, classes) => {
    expect(element.classes()).toEqual(expect.arrayContaining(classes));
};

describe('CategorySidebar', () => {
    it('uses brand-blue selected states and badges in chip navigation', async () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [{ id: 1, name: 'Soups' }],
                items: [{ id: 11, category_id: 1 }],
                selectedCategory: 1,
            },
        });

        let [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(categoryButton, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(categoryButton.find('[data-slot="badge"]'), ['bg-white/20', 'text-white']);
        expectClasses(allButton, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);

        await wrapper.setProps({ selectedCategory: null });
        [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(allButton, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(allButton.find('[data-slot="badge"]'), ['bg-white/20', 'text-white']);
        expectClasses(categoryButton, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);
    });

    it('prepares mobile chip row wrapping without forcing horizontal page overflow', () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [
                    { id: 1, name: 'Soups' },
                    { id: 2, name: 'Mains' },
                    { id: 3, name: 'Bakery' },
                ],
                items: [
                    { id: 11, category_id: 1 },
                    { id: 12, category_id: 2 },
                    { id: 13, category_id: 3 },
                ],
                selectedCategory: null,
            },
        });

        const nav = wrapper.get('nav');
        const row = wrapper.get('[data-testid="category-chip-row"]');

        expectClasses(nav, ['max-[639px]:overflow-x-clip']);
        expectClasses(row, ['max-[639px]:w-full', 'max-[639px]:min-w-0', 'max-[639px]:flex-wrap']);
    });
});
