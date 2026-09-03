<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import { can } from '@/lib/can';
import type { Paginated } from '@/types/rbac';
import type { Category } from '@/types/catalog';

const props = defineProps<{
    categories: Paginated<Category>;
    search: string;
}>();

const emit = defineEmits<{
    create: [];
    edit: [category: Category];
}>();

const search = ref(props.search);

const runSearch = () => {
    router.get(CategoryController.index.url(), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const destroy = (category: Category) => {
    if (confirm(trans('Delete the ":name" category? This cannot be undone.', { name: category.category_name }))) {
        router.delete(CategoryController.destroy.url(category.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <TextInput
                v-model="search"
                type="search"
                :placeholder="$t('Search categories...')"
                class="w-full max-w-xs"
                @keyup.enter="runSearch"
                @blur="runSearch"
            />

            <SecondaryButton v-if="can('CATEGORY:WRITE')" @click="emit('create')">
                {{ $t('Create Category') }}
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="category in categories.data" :key="category.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ category.category_name }}</td>
                    <td class="px-3 py-2 text-sm text-right space-x-2">
                        <button
                            v-if="can('CATEGORY:WRITE')"
                            type="button"
                            class="text-indigo-600 hover:text-indigo-900"
                            @click="emit('edit', category)"
                        >
                            {{ $t('Edit') }}
                        </button>
                        <DangerButton v-if="can('CATEGORY:WRITE')" @click="destroy(category)">
                            {{ $t('Delete') }}
                        </DangerButton>
                    </td>
                </tr>
                <tr v-if="categories.data.length === 0">
                    <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No categories found.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="categories.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in categories.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="px-3 py-1 text-sm text-gray-400"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="px-3 py-1 text-sm rounded-md"
                    :class="link.active ? 'bg-indigo-500 text-white' : 'text-gray-700 hover:bg-gray-100'"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
