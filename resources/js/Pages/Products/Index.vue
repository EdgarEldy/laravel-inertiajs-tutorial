<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated } from '@/types/rbac';
import type { Category, Product } from '@/types/catalog';

defineProps<{
    products: Paginated<Product>;
    categories: Category[];
    filters: { search: string; category: number | null };
}>();

const showModal = ref(false);
const editingProduct = ref<Product | null>(null);

const openCreate = () => {
    editingProduct.value = null;
    showModal.value = true;
};

const openEdit = (product: Product) => {
    editingProduct.value = product;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingProduct.value = null;
};
</script>

<template>
    <AppLayout :title="$t('Products')">
        <Head :title="$t('Products')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Products') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data
                    :products="products"
                    :categories="categories"
                    :search="filters.search"
                    :category="filters.category"
                    @create="openCreate"
                    @edit="openEdit"
                />
            </div>
        </div>

        <!--
            No `:key` here, same reasoning as Categories/Index.vue - see the
            comment there for why keying this Form/Modal instance on the
            editing target breaks the modal's own click handling.
        -->
        <Form
            :show="showModal"
            :product="editingProduct"
            :categories="categories"
            @close="closeModal"
        />
    </AppLayout>
</template>
