<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
import type { Permission } from '@/types/rbac';

const props = defineProps<{
    show: boolean;
    permission: Permission | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

// A single `useForm()` covers both create and edit - the modal posts to
// `store` when `permission` is null, `update` when editing an existing one.
const form = useForm({
    resource: props.permission?.resource ?? '',
    action: props.permission?.action ?? '',
});

const submit = () => {
    if (props.permission) {
        form.put(PermissionController.update.url(props.permission.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(PermissionController.store.url(), {
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
            {{ permission ? 'Edit Permission' : 'Create Permission' }}
        </template>

        <template #content>
            <div>
                <InputLabel for="resource" value="Resource" />
                <TextInput
                    id="resource"
                    v-model="form.resource"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="CATEGORY"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.resource" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="action" value="Action" />
                <TextInput
                    id="action"
                    v-model="form.action"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="READ"
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.action" class="mt-2" />
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="close">
                Cancel
            </SecondaryButton>

            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="submit"
            >
                {{ permission ? 'Save' : 'Create' }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
