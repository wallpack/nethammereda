import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchCatalogData } from '@/api/catalog';

export const useCatalogStore = defineStore('catalog', () => {
    const cycle = ref(null);
    const categories = ref([]);
    const items = ref([]);
    const search = ref('');
    const selectedCategory = ref(null);

    const isOpenForOrdering = computed(() => Boolean(cycle.value?.is_open_for_ordering));

    const weeklyDeadlineLabel = computed(() => {
        if (cycle.value?.closes_at) {
            const closeDate = new Date(cycle.value.closes_at);

            if (!Number.isNaN(closeDate.getTime())) {
                return closeDate.toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            }
        }

        if (!cycle.value?.starts_at) {
            return 'Пятница, 12:00';
        }

        const start = new Date(cycle.value.starts_at);
        if (Number.isNaN(start.getTime())) {
            return 'Пятница, 12:00';
        }

        const mondayBasedDay = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - mondayBasedDay + 4);
        start.setHours(12, 0, 0, 0);

        return start.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    });

    const filteredItems = computed(() => {
        const q = search.value.trim().toLowerCase();

        return items.value.filter((item) => {
            const categoryOk = selectedCategory.value ? item.category_id === selectedCategory.value : true;
            const searchableText = [
                item.title,
                item.description,
                item.composition,
                item.category?.name,
            ].filter(Boolean).join(' ').toLowerCase();
            const searchOk = q ? searchableText.includes(q) : true;

            return categoryOk && searchOk;
        });
    });

    const categoryItemCount = (categoryId) => {
        return items.value.filter((item) => item.category_id === categoryId).length;
    };

    const loadCatalogData = async () => {
        const catalogData = await fetchCatalogData();

        cycle.value = catalogData.cycle;
        categories.value = catalogData.categories;
        items.value = catalogData.items;

        return catalogData;
    };

    return {
        cycle,
        categories,
        items,
        search,
        selectedCategory,
        isOpenForOrdering,
        weeklyDeadlineLabel,
        filteredItems,
        categoryItemCount,
        loadCatalogData,
    };
});
