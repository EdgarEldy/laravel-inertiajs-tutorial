<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated } from '@/types/rbac';
import type { Customer } from '@/types/catalog';

defineProps<{
    customers: Paginated<Customer>;
    filters: { search: string };
}>();

const showModal = ref(false);
const editingCustomer = ref<Customer | null>(null);

const openCreate = () => {
    editingCustomer.value = null;
    showModal.value = true;
};

const openEdit = (customer: Customer) => {
    editingCustomer.value = customer;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCustomer.value = null;
};
</script>

<template>
    <AppLayout :title="$t('Customers')">
        <Head :title="$t('Customers')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Customers') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data :customers="customers" :search="filters.search" @create="openCreate" @edit="openEdit" />
            </div>
        </div>

        <!--
            No `:key="editingCustomer?.id ?? 'create'"` here, deliberately -
            see the same comment on Categories/Index.vue for why keying this
            reintroduces the modal-never-opens bug fixed there.
        -->
        <Form
            :show="showModal"
            :customer="editingCustomer"
            @close="closeModal"
        />
    </AppLayout>
</template>
