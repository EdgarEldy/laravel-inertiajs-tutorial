<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated } from '@/types/rbac';
import type { Customer, Order, Product } from '@/types/catalog';

defineProps<{
    orders: Paginated<Order>;
    // Only id + display name - see OrderController::index()'s own comment.
    customers: Pick<Customer, 'id' | 'first_name' | 'last_name'>[];
    products: Pick<Product, 'id' | 'product_name'>[];
}>();

// Orders are create-only: no `editingOrder` state, no `edit` event to wire
// up - unlike every other resource's Index.vue, there is no update/delete
// route for orders at all.
const showModal = ref(false);

const openCreate = () => {
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};
</script>

<template>
    <AppLayout :title="$t('Orders')">
        <Head :title="$t('Orders')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Orders') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data :orders="orders" @create="openCreate" />
            </div>
        </div>

        <Form
            :show="showModal"
            :customers="customers"
            :products="products"
            @close="closeModal"
        />
    </AppLayout>
</template>
