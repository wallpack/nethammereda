import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CategorySidebar from './CategorySidebar.vue';

const expectClasses = (element, classes) => {
    expect(element.classes()).toEqual(expect.arrayContaining(classes));
};

describe('CategorySidebar', () => {
    it('uses brand-blue selected states and badges in mobile and desktop navigation', async () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [{ id: 1, name: 'Soups' }],
                items: [{ id: 11, category_id: 1 }],
                selectedCategory: 1,
            },
        });

        let [mobileAll, mobileCategory, desktopAll, desktopCategory] = wrapper.findAll('button');

        expectClasses(mobileCategory, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(desktopCategory, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(mobileCategory.find('span'), ['bg-white/20', 'text-white']);
        expectClasses(desktopCategory.find('[data-slot="badge"]'), ['bg-white/20', 'text-white']);
        expectClasses(mobileAll, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);
        expectClasses(desktopAll, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);

        await wrapper.setProps({ selectedCategory: null });
        [mobileAll, mobileCategory, desktopAll, desktopCategory] = wrapper.findAll('button');

        expectClasses(mobileAll, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(desktopAll, ['bg-blue-600', 'text-white', 'shadow-sm', 'ring-1', 'ring-blue-500/20']);
        expectClasses(mobileAll.find('span'), ['bg-white/20', 'text-white']);
        expectClasses(desktopAll.find('[data-slot="badge"]'), ['bg-white/20', 'text-white']);
        expectClasses(mobileCategory, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);
        expectClasses(desktopCategory, ['text-slate-700', 'hover:bg-blue-50', 'hover:text-blue-700']);
    });
});
