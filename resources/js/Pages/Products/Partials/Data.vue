<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import { can } from '@/lib/can';
import type { Paginated } from '@/types/rbac';
import type { Category, Product } from '@/types/catalog';

const props = defineProps<{
    products: Paginated<Product>;
    categories: Category[];
    search: string;
    category: number | null;
}>();

const emit = defineEmits<{
    create: [];
    edit: [product: Product];
}>();

const search = ref(props.search);
const categoryFilter = ref<string>(props.category ? String(props.category) : '');

const runSearch = () => {
    router.get(ProductController.index.url(), {
        search: search.value,
        category: categoryFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const destroy = (product: Product) => {
    if (confirm(trans('Delete the ":name" product? This cannot be undone.', { name: product.product_name }))) {
        router.delete(ProductController.destroy.url(product.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <TextInput
                    v-model="search"
                    type="search"
                    :placeholder="$t('Search products...')"
                    class="w-full max-w-xs"
                    @keyup.enter="runSearch"
                    @blur="runSearch"
                />

                <select
                    v-model="categoryFilter"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    @change="runSearch"
                >
                    <option value="">{{ $t('All categories') }}</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.category_name }}
                    </option>
                </select>
            </div>

            <SecondaryButton v-if="can('PRODUCT:WRITE')" @click="emit('create')">
                {{ $t('Create Product') }}
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Category') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Unit price') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="product in products.data" :key="product.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ product.product_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">{{ product.category?.category_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">{{ product.unit_price }}</td>
                    <td class="px-3 py-2 text-sm text-right space-x-2">
                        <button
                            v-if="can('PRODUCT:WRITE')"
                            type="button"
                            class="text-indigo-600 hover:text-indigo-900"
                            @click="emit('edit', product)"
                        >
                            {{ $t('Edit') }}
                        </button>
                        <DangerButton v-if="can('PRODUCT:WRITE')" @click="destroy(product)">
                            {{ $t('Delete') }}
                        </DangerButton>
                    </td>
                </tr>
                <tr v-if="products.data.length === 0">
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No products found.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="products.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in products.links" :key="link.label">
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
