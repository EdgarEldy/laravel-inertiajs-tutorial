<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import type { Paginated, User } from '@/types/rbac';

const props = defineProps<{
    users: Paginated<User>;
    filters: { search: string };
}>();

const search = ref(props.filters.search);

const runSearch = () => {
    router.get(UserController.index.url(), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <AppLayout :title="$t('Users')">
        <Head :title="$t('Users')" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $t('Users') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="mb-4">
                        <TextInput
                            v-model="search"
                            type="search"
                            :placeholder="$t('Search users...')"
                            class="w-full max-w-xs"
                            @keyup.enter="runSearch"
                            @blur="runSearch"
                        />
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Email') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Roles') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-3 py-2 text-sm text-gray-900">{{ user.name }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">{{ user.email }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    {{ (user.roles ?? []).map((role) => role.role_name).join(', ') }}
                                </td>
                                <td class="px-3 py-2 text-sm text-right">
                                    <Link :href="UserController.show.url(user.id)" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $t('View') }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No users found.') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="users.links.length > 3" class="flex flex-wrap gap-1 mt-4">
                        <template v-for="link in users.links" :key="link.label">
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
            </div>
        </div>
    </AppLayout>
</template>
