<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
import { can } from '@/lib/can';
import type { Paginated, Permission } from '@/types/rbac';

const props = defineProps<{
    permissions: Paginated<Permission>;
    search: string;
}>();

const emit = defineEmits<{
    create: [];
    edit: [permission: Permission];
}>();

const search = ref(props.search);

const runSearch = () => {
    router.get(PermissionController.index.url(), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const destroy = (permission: Permission) => {
    if (confirm(trans('Delete the ":name" permission? This cannot be undone.', { name: permission.name }))) {
        router.delete(PermissionController.destroy.url(permission.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <TextInput
                v-model="search"
                type="search"
                :placeholder="$t('Search permissions...')"
                class="w-full max-w-xs"
                @keyup.enter="runSearch"
                @blur="runSearch"
            />

            <SecondaryButton v-if="can('PERMISSION:WRITE')" @click="emit('create')">
                {{ $t('Create Permission') }}
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Roles') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="permission in permissions.data" :key="permission.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ permission.name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-600">{{ permission.roles_count }}</td>
                    <td class="px-3 py-2 text-sm text-right space-x-2">
                        <button
                            v-if="can('PERMISSION:WRITE')"
                            type="button"
                            class="text-indigo-600 hover:text-indigo-900"
                            @click="emit('edit', permission)"
                        >
                            {{ $t('Edit') }}
                        </button>
                        <DangerButton v-if="can('PERMISSION:WRITE')" @click="destroy(permission)">
                            {{ $t('Delete') }}
                        </DangerButton>
                    </td>
                </tr>
                <tr v-if="permissions.data.length === 0">
                    <td colspan="3" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No permissions found.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="permissions.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in permissions.links" :key="link.label">
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
