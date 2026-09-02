<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { index as rolesIndex } from '@/routes/roles';
import type { Permission, Role } from '@/types/rbac';

const props = defineProps<{
    role: Role;
    permissions: Permission[];
    errors: Record<string, string>;
}>();

const assignedIds = computed(() => new Set((props.role.permissions ?? []).map((permission) => permission.id)));

const groupedPermissions = computed(() => {
    const groups: Record<string, Permission[]> = {};

    for (const permission of props.permissions) {
        groups[permission.resource] ??= [];
        groups[permission.resource].push(permission);
    }

    return groups;
});

const toggle = (permission: Permission, checked: boolean) => {
    if (checked) {
        router.post(RoleController.assignPermission.url([props.role.id, permission.id]), {}, { preserveScroll: true });
    } else {
        router.delete(RoleController.removePermission.url([props.role.id, permission.id]), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="`${role.role_name} - Permissions`">
        <Head :title="`${role.role_name} - Permissions`" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Permissions for "{{ role.role_name }}"
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Link :href="rolesIndex.url()" class="text-sm text-indigo-600 hover:text-indigo-900">
                    &larr; Back to roles
                </Link>

                <InputError :message="errors.permission" class="mt-2" />

                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                    <div v-for="(items, resource) in groupedPermissions" :key="resource">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase mb-2">{{ resource }}</h3>
                        <div class="space-y-2">
                            <label v-for="permission in items" :key="permission.id" class="flex items-center">
                                <Checkbox
                                    :checked="assignedIds.has(permission.id)"
                                    @update:checked="(checked) => toggle(permission, checked as boolean)"
                                />
                                <span class="ms-2 text-sm text-gray-700">{{ permission.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
