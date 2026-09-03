import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const { routerGetMock, routerDeleteMock, postMock, putMock, canMock } = vi.hoisted(() => ({
    routerGetMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    postMock: vi.fn(),
    putMock: vi.fn(),
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<head-stub><slot /></head-stub>' },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: routerGetMock,
        delete: routerDeleteMock,
    },
    useForm: (initial) => reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        put: putMock,
        reset() {
            Object.keys(initial).forEach((key) => {
                this[key] = initial[key];
            });
        },
        clearErrors() {
            this.errors = {};
        },
    }),
}));

vi.mock('laravel-vue-i18n', () => ({
    trans: (key) => key,
}));

vi.mock('@/lib/can', () => ({
    can: canMock,
}));

import Index from './Index.vue';

const makeRoles = (overrides = {}) => ({
    data: [
        { id: 1, role_name: 'ADMIN', users_count: 2 },
        { id: 2, role_name: 'USER', users_count: 5 },
    ],
    links: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 2,
    from: 1,
    to: 2,
    ...overrides,
});

// Stubs only the innermost `Modal.vue` (a real <dialog> element driven by
// `dialog.showModal()`/`dialog.close()`), not `DialogModal.vue` or
// `Form.vue` - jsdom in this project does not implement
// `HTMLDialogElement.prototype.showModal`, so exercising it here would fail
// on a test-environment gap, not an application bug. `DialogModal.vue`'s own
// title/content/footer slots (and everything `Form.vue` puts inside them)
// still render for real through this stub's plain default slot.
const mountIndex = (props = {}) => mount(Index, {
    props: {
        roles: makeRoles(),
        filters: { search: '' },
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            AppLayout: { template: '<div><slot name="header" /><slot /></div>' },
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Roles/Index.vue integration (real Data + Form children)', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders the roles table from the real Data child', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('ADMIN');
        expect(wrapper.text()).toContain('USER');
    });

    it('the Form starts in create mode (blank role_name) before any row is opened for edit', () => {
        const wrapper = mountIndex();

        const input = wrapper.find('input#role_name');
        expect(input.exists()).toBe(true);
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Role');
    });

    it('clicking a row\'s Edit action in Data actually opens Form pre-filled with that row\'s data', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        const input = wrapper.find('input#role_name');
        expect(input.element.value).toBe('USER');
        expect(wrapper.text()).toContain('Edit Role');
    });

    it('closing the modal after opening it for edit clears editingRole state back to a blank create form', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        expect(wrapper.find('input#role_name').element.value).toBe('ADMIN');

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        // Re-opening via "Create" afterwards must show a blank field, not
        // the previously-edited role's name leaking through.
        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Role');
        await createButton.trigger('click');

        const input = wrapper.find('input#role_name');
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Role');
    });

    it('submitting the edit form from within Index posts through to RoleController.update with the real role id', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await saveButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/roles/2', expect.objectContaining({ preserveScroll: true }));
    });
});
