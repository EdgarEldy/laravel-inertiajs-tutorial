<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import type { Role } from '@/types/rbac';

const props = defineProps<{
    show: boolean;
    role: Role | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

// A single `useForm()` covers both create and edit - the modal posts to
// `store` when `role` is null, `update` when editing an existing one.
const form = useForm({
    role_name: props.role?.role_name ?? '',
});

const submit = () => {
    if (props.role) {
        form.put(RoleController.update.url(props.role.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(RoleController.store.url(), {
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
            {{ role ? $t('Edit Role') : $t('Create Role') }}
        </template>

        <template #content>
            <div>
                <InputLabel for="role_name" :value="$t('Role name')" />
                <TextInput
                    id="role_name"
                    v-model="form.role_name"
                    type="text"
                    class="mt-1 block w-full"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.role_name" class="mt-2" />
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
                {{ role ? $t('Save') : $t('Create') }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
