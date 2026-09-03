import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const { postMock, putMock, resetMock, clearErrorsMock, useFormMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    resetMock: vi.fn(),
    clearErrorsMock: vi.fn(),
    useFormMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: useFormMock,
}));

import Form from './Form.vue';

function fakeUseForm(initial) {
    return reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        put: putMock,
        reset: resetMock,
        clearErrors: clearErrorsMock,
    });
}

const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        permission: null,
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Permissions/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        putMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('initializes empty resource/action fields when opened for create (permission is null)', () => {
        mountForm({ permission: null });

        expect(useFormMock).toHaveBeenCalledWith({ resource: '', action: '' });
    });

    it('pre-fills resource/action from the permission being edited', () => {
        mountForm({ permission: { id: 3, resource: 'CATEGORY', action: 'READ', name: 'CATEGORY:READ' } });

        expect(useFormMock).toHaveBeenCalledWith({ resource: 'CATEGORY', action: 'READ' });
    });

    it('shows the create title/button when permission is null, the edit title/button otherwise', () => {
        const createWrapper = mountForm({ permission: null });
        expect(createWrapper.text()).toContain('Create Permission');

        const editWrapper = mountForm({ permission: { id: 3, resource: 'CATEGORY', action: 'READ' } });
        expect(editWrapper.text()).toContain('Edit Permission');
    });

    it('posts to PermissionController.store on create', async () => {
        const wrapper = mountForm({ permission: null });

        await wrapper.find('input#resource').setValue('PRODUCT');
        await wrapper.find('input#action').setValue('WRITE');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/permissions', expect.objectContaining({ preserveScroll: true }));
        expect(putMock).not.toHaveBeenCalled();
    });

    it('puts to PermissionController.update on edit', async () => {
        const wrapper = mountForm({ permission: { id: 7, resource: 'CATEGORY', action: 'READ' } });

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await submitButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/permissions/7', expect.objectContaining({ preserveScroll: true }));
        expect(postMock).not.toHaveBeenCalled();
    });

    it('renders server-side validation errors passed in via form.errors for both fields', () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.resource = 'The resource field is required.';
            form.errors.action = 'The action field is required.';
            return form;
        });

        const wrapper = mountForm({ permission: null });

        expect(wrapper.text()).toContain('The resource field is required.');
        expect(wrapper.text()).toContain('The action field is required.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm({ permission: null });

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
