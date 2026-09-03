<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { index as usersIndex } from '@/routes/users';
import { can } from '@/lib/can';
import type { Role, User } from '@/types/rbac';

const props = defineProps<{
    user: User;
    availableRoles: Role[];
    errors: Record<string, string>;
}>();

const assignedIds = computed(() => new Set((props.user.roles ?? []).map((role) => role.id)));
const unassignedRoles = computed(() => props.availableRoles.filter((role) => !assignedIds.value.has(role.id)));

const selectedRoleId = ref<number | ''>('');

const assignRole = () => {
    if (selectedRoleId.value === '') {
        return;
    }

    router.post(UserController.assignRole.url([props.user.id, selectedRoleId.value]), {}, {
        preserveScroll: true,
        onSuccess: () => (selectedRoleId.value = ''),
    });
};

const removeRole = (role: Role) => {
    if (confirm(trans('Remove the ":role" role from :user?', { role: role.role_name, user: props.user.name }))) {
        router.delete(UserController.removeRole.url([props.user.id, role.id]), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="user.name">
        <Head :title="user.name" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ user.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Link :href="usersIndex.url()" class="text-sm text-indigo-600 hover:text-indigo-900">
                    &larr; {{ $t('Back to users') }}
                </Link>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</dt>
                            <dd class="text-sm text-gray-900">{{ user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">{{ $t('Email') }}</dt>
                            <dd class="text-sm text-gray-900">{{ user.email }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase mb-4">{{ $t('Assigned roles') }}</h3>

                    <InputError :message="errors.role" class="mb-4" />

                    <ul class="divide-y divide-gray-200 mb-6">
                        <li v-for="role in user.roles ?? []" :key="role.id" class="flex items-center justify-between py-2">
                            <span class="text-sm text-gray-900">{{ role.role_name }}</span>
                            <DangerButton v-if="can('USER:WRITE')" @click="removeRole(role)">
                                {{ $t('Remove') }}
                            </DangerButton>
                        </li>
                        <li v-if="(user.roles ?? []).length === 0" class="py-2 text-sm text-gray-500">
                            {{ $t('No roles assigned.') }}
                        </li>
                    </ul>

                    <form v-if="can('USER:WRITE')" class="flex items-end gap-3" @submit.prevent="assignRole">
                        <div class="flex-1">
                            <InputLabel for="role_id" :value="$t('Assign a role')" />
                            <select
                                id="role_id"
                                v-model="selectedRoleId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="" disabled>{{ $t('Select a role') }}</option>
                                <option v-for="role in unassignedRoles" :key="role.id" :value="role.id">
                                    {{ role.role_name }}
                                </option>
                            </select>
                        </div>

                        <PrimaryButton :disabled="selectedRoleId === ''" type="submit">
                            {{ $t('Assign') }}
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
