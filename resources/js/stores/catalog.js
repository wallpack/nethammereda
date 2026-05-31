import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchCatalogData } from '@/api/catalog';

export const useCatalogStore = defineStore('catalog', () => {
    const cycle = ref(null);
    const categories = ref([]);
    const items = ref([]);
    const search = ref('');
    const selectedCategory = ref(null);

    const isOpenForOrdering = computed(() => Boolean(
        cycle.value?.accepting_orders
        ?? cycle.value?.can_order
        ?? cycle.value?.is_orderable
        ?? cycle.value?.is_open_for_ordering,
    ));

    const deadlinePassed = computed(() => Boolean(cycle.value?.deadline_passed));

    const availabilityLabel = computed(() => {
        if (cycle.value?.availability_label) {
            return cycle.value.availability_label;
        }

        if (isOpenForOrdering.value) {
            return 'Приём открыт';
        }

        if (cycle.value?.effective_state === 'upcoming') {
            return 'Скоро откроется';
        }

        if (cycle.value?.status === 'open' && deadlinePassed.value) {
            return 'Дедлайн прошел';
        }

        const labels = {
            draft: 'Приём закрыт',
            open: 'Приём закрыт',
            closed: 'Приём закрыт',
            sent_to_supplier: 'Отправлен поставщику',
            delivered: 'Доставлен',
            archived: 'Архивирован',
        };

        return labels[cycle.value?.status] ?? 'Недельный цикл не создан';
    });

    const availabilityDescription = computed(() => {
        if (cycle.value?.availability_description) {
            return cycle.value.availability_description;
        }

        if (isOpenForOrdering.value) {
            return 'Можно добавлять блюда до дедлайна.';
        }

        return 'Прием заказов завершен.';
    });

    const weeklyDeadlineLabel = computed(() => {
        if (cycle.value?.deadline_display_full) {
            return cycle.value.deadline_display_full;
        }

        if (cycle.value?.deadline_display) {
            return cycle.value.deadline_display;
        }

        if (cycle.value?.deadline_date && cycle.value?.deadline_time) {
            return `${cycle.value.deadline_date}, ${cycle.value.deadline_time}`;
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
        deadlinePassed,
        availabilityLabel,
        availabilityDescription,
        weeklyDeadlineLabel,
        filteredItems,
        categoryItemCount,
        loadCatalogData,
    };
});
