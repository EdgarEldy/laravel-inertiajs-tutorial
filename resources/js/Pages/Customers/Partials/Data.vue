<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import { can } from '@/lib/can';
import type { Paginated } from '@/types/rbac';
import type { Customer } from '@/types/catalog';

const props = defineProps<{
    customers: Paginated<Customer>;
    search: string;
}>();

const emit = defineEmits<{
    create: [];
    edit: [customer: Customer];
}>();

const search = ref(props.search);

const runSearch = () => {
    router.get(CustomerController.index.url(), { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const destroy = (customer: Customer) => {
    if (confirm(trans('Delete the ":name" customer? This cannot be undone.', { name: `${customer.first_name} ${customer.last_name}` }))) {
        router.delete(CustomerController.destroy.url(customer.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <TextInput
                v-model="search"
                type="search"
                :placeholder="$t('Search customers...')"
                class="w-full max-w-xs"
                @keyup.enter="runSearch"
                @blur="runSearch"
            />

            <SecondaryButton v-if="can('CUSTOMER:WRITE')" @click="emit('create')">
                {{ $t('Create Customer') }}
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Name') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Telephone') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Email') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Address') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $t('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="customer in customers.data" :key="customer.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ customer.first_name }} {{ customer.last_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-900">{{ customer.telephone }}</td>
                    <td class="px-3 py-2 text-sm text-gray-900">{{ customer.email }}</td>
                    <td class="px-3 py-2 text-sm text-gray-900">{{ customer.address }}</td>
                    <td class="px-3 py-2 text-sm text-right space-x-2">
                        <button
                            v-if="can('CUSTOMER:WRITE')"
                            type="button"
                            class="text-indigo-600 hover:text-indigo-900"
                            @click="emit('edit', customer)"
                        >
                            {{ $t('Edit') }}
                        </button>
                        <DangerButton v-if="can('CUSTOMER:WRITE')" @click="destroy(customer)">
                            {{ $t('Delete') }}
                        </DangerButton>
                    </td>
                </tr>
                <tr v-if="customers.data.length === 0">
                    <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No customers found.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="customers.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in customers.links" :key="link.label">
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
