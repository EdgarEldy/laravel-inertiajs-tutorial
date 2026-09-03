<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import type { Customer } from '@/types/catalog';

const props = defineProps<{
    show: boolean;
    customer: Customer | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

// A single `useForm()` covers both create and edit - the modal posts to
// `store` when `customer` is null, `update` when editing an existing one.
// This component instance is never remounted (`Index.vue` deliberately
// passes no `:key`, see the comment there), so the fields are synced from
// `customer` here via a `watch` on `show` each time the modal opens,
// instead of relying on a fresh `useForm()` call per editing target.
const form = useForm({
    first_name: '',
    last_name: '',
    telephone: '',
    email: '',
    address: '',
});

watch(() => props.show, (show) => {
    if (show) {
        form.first_name = props.customer?.first_name ?? '';
        form.last_name = props.customer?.last_name ?? '';
        form.telephone = props.customer?.telephone ?? '';
        form.email = props.customer?.email ?? '';
        form.address = props.customer?.address ?? '';
        form.clearErrors();
    }
});

const submit = () => {
    if (props.customer) {
        form.put(CustomerController.update.url(props.customer.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(CustomerController.store.url(), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    }
};

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <DialogModal :show="show" @close="close">
        <template #title>
            {{ customer ? $t('Edit Customer') : $t('Create Customer') }}
        </template>

        <template #content>
            <div class="space-y-4">
                <div>
                    <InputLabel for="first_name" :value="$t('First name')" />
                    <TextInput
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.first_name" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="last_name" :value="$t('Last name')" />
                    <TextInput
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-1 block w-full"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.last_name" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="telephone" :value="$t('Telephone')" />
                    <TextInput
                        id="telephone"
                        v-model="form.telephone"
                        type="text"
                        class="mt-1 block w-full"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.telephone" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="email" :value="$t('Email')" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="address" :value="$t('Address')" />
                    <TextInput
                        id="address"
                        v-model="form.address"
                        type="text"
                        class="mt-1 block w-full"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.address" class="mt-2" />
                </div>
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="close">
                {{ $t('Cancel') }}
            </SecondaryButton>

            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="submit"
            >
                {{ customer ? $t('Save') : $t('Create') }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
