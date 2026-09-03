import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

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

/**
 * A minimal, controllable stand-in for Inertia's real useForm(): reactive
 * enough to drive v-model in the template, but with post/put/reset/clearErrors
 * as plain spies so submission behavior can be asserted directly instead of
 * relying on a real (network-driving) form implementation.
 */
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

// Stubs only the innermost `Modal.vue` (a real <dialog> element driven by
// `dialog.showModal()`/`dialog.close()`), not `DialogModal.vue` - jsdom in
// this project does not implement `HTMLDialogElement.prototype.showModal`,
// so exercising it here would fail on a test-environment gap, not an
// application bug. See Roles/Index.spec.js for the established precedent of
// this exact stub.
const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        category: null,
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Categories/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        putMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('always initializes useForm with a blank category_name, regardless of the category prop (fields are synced later via a watch on "show", not at construction time)', () => {
        mountForm({ show: false, category: { id: 5, category_name: 'MANAGER' } });

        expect(useFormMock).toHaveBeenCalledWith({ category_name: '' });
    });

    it('does NOT pre-fill category_name just from being mounted with show already true - the sync only happens on a false-to-true transition of "show"', () => {
        // Unlike Roles/Permissions (:key-based remount, fresh useForm() call
        // per editing target), Categories/Partials/Form.vue is never
        // remounted and relies on a `watch(() => props.show, ...)`. That
        // watch does not run immediately, so mounting directly with
        // `show: true` (no prior `false` state within this instance) never
        // triggers it.
        const wrapper = mountForm({ show: true, category: { id: 5, category_name: 'MANAGER' } });

        const input = wrapper.find('input#category_name');
        expect(input.element.value).toBe('');
    });

    it('pre-fills category_name from the category being edited once "show" transitions from false to true', async () => {
        const wrapper = mountForm({ show: false, category: { id: 5, category_name: 'MANAGER' } });

        await wrapper.setProps({ show: true });
        await nextTick();

        const input = wrapper.find('input#category_name');
        expect(input.element.value).toBe('MANAGER');
    });

    it('resyncs to a blank field when "show" transitions from false to true with category null (create mode)', async () => {
        const wrapper = mountForm({ show: false, category: null });

        await wrapper.setProps({ show: true });
        await nextTick();

        const input = wrapper.find('input#category_name');
        expect(input.element.value).toBe('');
    });

    it('shows the create title/button when category is null, the edit title/button otherwise', () => {
        const createWrapper = mountForm({ category: null });
        expect(createWrapper.text()).toContain('Create Category');

        const editWrapper = mountForm({ category: { id: 5, category_name: 'MANAGER' } });
        expect(editWrapper.text()).toContain('Edit Category');
    });

    it('posts to CategoryController.store on create', async () => {
        const wrapper = mountForm({ show: true, category: null });

        await wrapper.find('input#category_name').setValue('Furniture');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/categories', expect.objectContaining({ preserveScroll: true }));
        expect(putMock).not.toHaveBeenCalled();
    });

    it('puts to CategoryController.update on edit', async () => {
        const wrapper = mountForm({ show: false, category: { id: 9, category_name: 'MANAGER' } });
        await wrapper.setProps({ show: true });
        await nextTick();

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await submitButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/categories/9', expect.objectContaining({ preserveScroll: true }));
        expect(postMock).not.toHaveBeenCalled();
    });

    it('renders a server-side validation error passed in via form.errors', async () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.category_name = 'The category name has already been taken.';
            return form;
        });

        const wrapper = mountForm({ category: null });

        expect(wrapper.text()).toContain('The category name has already been taken.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm({ category: null });

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
