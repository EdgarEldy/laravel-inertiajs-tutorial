<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated, Permission } from '@/types/rbac';

defineProps<{
    permissions: Paginated<Permission>;
    filters: { search: string };
}>();

const showModal = ref(false);
const editingPermission = ref<Permission | null>(null);

const openCreate = () => {
    editingPermission.value = null;
    showModal.value = true;
};

const openEdit = (permission: Permission) => {
    editingPermission.value = permission;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingPermission.value = null;
};
</script>

<template>
    <AppLayout title="Permissions">
        <Head title="Permissions" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Permissions
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data :permissions="permissions" :search="filters.search" @create="openCreate" @edit="openEdit" />
            </div>
        </div>

        <Form
            :key="editingPermission?.id ?? 'create'"
            :show="showModal"
            :permission="editingPermission"
            @close="closeModal"
        />
    </AppLayout>
</template>
