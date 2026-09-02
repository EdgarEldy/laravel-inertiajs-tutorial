<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { can } from '@/lib/can';
import type { Paginated, Role } from '@/types/rbac';

const props = defineProps<{
    roles: Paginated<Role>;
    search: string;
}>();

const emit = defineEmits<{
    create: [];
    edit: [role: Role];
}>();

const search = ref(props.search);

const runSearch = () => {
    router.get(RoleController.index.url(), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const destroy = (role: Role) => {
    if (confirm(`Delete the "${role.role_name}" role? This cannot be undone.`)) {
        router.delete(RoleController.destroy.url(role.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <TextInput
                v-model="search"
                type="search"
                placeholder="Search roles..."
                class="w-full max-w-xs"
                @keyup.enter="runSearch"
                @blur="runSearch"
            />

            <SecondaryButton v-if="can('ROLE:WRITE')" @click="emit('create')">
                Create Role
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Users</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="role in roles.data" :key="role.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ role.role_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-600">{{ role.users_count }}</td>
                    <td class="px-3 py-2 text-sm text-right space-x-2">
                        <Link
                            v-if="can('ROLE:WRITE')"
                            :href="RoleController.permissions.url(role.id)"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            Permissions
                        </Link>
                        <button
                            v-if="can('ROLE:WRITE')"
                            type="button"
                            class="text-indigo-600 hover:text-indigo-900"
                            @click="emit('edit', role)"
                        >
                            Edit
                        </button>
                        <DangerButton v-if="can('ROLE:WRITE')" @click="destroy(role)">
                            Delete
                        </DangerButton>
                    </td>
                </tr>
                <tr v-if="roles.data.length === 0">
                    <td colspan="3" class="px-3 py-6 text-center text-sm text-gray-500">No roles found.</td>
                </tr>
            </tbody>
        </table>

        <div v-if="roles.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in roles.links" :key="link.label">
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
