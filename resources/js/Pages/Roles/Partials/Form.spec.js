import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const { postMock, putMock, resetMock, clearErrorsMock, useFormMock, lastFormOptions } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    resetMock: vi.fn(),
    clearErrorsMock: vi.fn(),
    useFormMock: vi.fn(),
    lastFormOptions: { current: null },
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: useFormMock,
}));

import Form from './Form.vue';

/**
 * A minimal, controllable stand-in for Inertia's real useForm(): reactive
 * enough to drive v-model in the template, but with post/put/reset/clearErrors
 * as plain spies so submission behavior can be asserted directly instead of
 * relying on a real (network-driving) form implementation.
 */
function fakeUseForm(initial) {
    const form = reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        put: putMock,
        reset: resetMock,
        clearErrors: clearErrorsMock,
    });
    return form;
}

const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        role: null,
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Roles/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        putMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('initializes an empty role_name when opened for create (role is null)', () => {
        mountForm({ role: null });

        expect(useFormMock).toHaveBeenCalledWith({ role_name: '' });
    });

    it('pre-fills role_name from the role being edited', () => {
        mountForm({ role: { id: 5, role_name: 'MANAGER', users_count: 0 } });

        expect(useFormMock).toHaveBeenCalledWith({ role_name: 'MANAGER' });
    });

    it('shows the create title/button when role is null, the edit title/button otherwise', () => {
        const createWrapper = mountForm({ role: null });
        expect(createWrapper.text()).toContain('Create Role');

        const editWrapper = mountForm({ role: { id: 5, role_name: 'MANAGER' } });
        expect(editWrapper.text()).toContain('Edit Role');
    });

    it('posts to RoleController.store on create', async () => {
        const wrapper = mountForm({ role: null });

        await wrapper.find('input#role_name').setValue('NEW_ROLE');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/roles', expect.objectContaining({ preserveScroll: true }));
        expect(putMock).not.toHaveBeenCalled();
    });

    it('puts to RoleController.update on edit', async () => {
        const wrapper = mountForm({ role: { id: 9, role_name: 'MANAGER' } });

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await submitButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/roles/9', expect.objectContaining({ preserveScroll: true }));
        expect(postMock).not.toHaveBeenCalled();
    });

    it('renders a server-side validation error passed in via form.errors', async () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.role_name = 'The role name has already been taken.';
            return form;
        });

        const wrapper = mountForm({ role: null });

        expect(wrapper.text()).toContain('The role name has already been taken.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm({ role: null });

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
