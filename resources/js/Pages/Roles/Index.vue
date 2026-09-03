<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated, Role } from '@/types/rbac';

defineProps<{
    roles: Paginated<Role>;
    filters: { search: string };
}>();

const showModal = ref(false);
const editingRole = ref<Role | null>(null);

const openCreate = () => {
    editingRole.value = null;
    showModal.value = true;
};

const openEdit = (role: Role) => {
    editingRole.value = role;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRole.value = null;
};
</script>

<template>
    <AppLayout :title="$t('Roles')">
        <Head :title="$t('Roles')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Roles') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data :roles="roles" :search="filters.search" @create="openCreate" @edit="openEdit" />
            </div>
        </div>

        <Form
            :key="editingRole?.id ?? 'create'"
            :show="showModal"
            :role="editingRole"
            @close="closeModal"
        />
    </AppLayout>
</template>
