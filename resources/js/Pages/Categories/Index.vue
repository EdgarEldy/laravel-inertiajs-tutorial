<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Data from './Partials/Data.vue';
import Form from './Partials/Form.vue';
import type { Paginated } from '@/types/rbac';
import type { Category } from '@/types/catalog';

defineProps<{
    categories: Paginated<Category>;
    filters: { search: string };
}>();

const showModal = ref(false);
const editingCategory = ref<Category | null>(null);

const openCreate = () => {
    editingCategory.value = null;
    showModal.value = true;
};

const openEdit = (category: Category) => {
    editingCategory.value = category;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCategory.value = null;
};
</script>

<template>
    <AppLayout :title="$t('Categories')">
        <Head :title="$t('Categories')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Categories') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Data :categories="categories" :search="filters.search" @create="openCreate" @edit="openEdit" />
            </div>
        </div>

        <!--
            No `:key="editingCategory?.id ?? 'create'"` here, deliberately
            unlike the otherwise-identical Roles/Permissions Form.vue usage:
            keying this remounts a brand-new Modal instance already holding
            `show=true` on every "Edit" click, and calling the native
            `<dialog>`'s `showModal()` in that same mount - before the
            browser has painted the fresh DOM even once - leaves Chromium's
            stacking of the modal's own `position: fixed` backdrop wrong
            relative to its (non-positioned) content sibling, silently
            swallowing every click on the modal's own buttons. Keeping one
            stable Form/Modal instance for the page's lifetime and letting
            `Partials/Form.vue` sync its own field(s) from the `category`
            prop via a `watch` on `show` instead avoids the remount (and the
            bug) entirely, reusing the same watch-driven open path that
            already works reliably for Create.
        -->
        <Form
            :show="showModal"
            :category="editingCategory"
            @close="closeModal"
        />
    </AppLayout>
</template>
