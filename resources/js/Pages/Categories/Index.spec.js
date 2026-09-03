import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

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
    trans: (key, replacements = {}) => Object.entries(replacements).reduce(
        (message, [search, value]) => message.replace(`:${search}`, value),
        key,
    ),
}));

vi.mock('@/lib/can', () => ({
    can: canMock,
}));

import Index from './Index.vue';

const makeCategories = (overrides = {}) => ({
    data: [
        { id: 1, category_name: 'Electronics', created_at: '2026-01-01T00:00:00.000000Z' },
        { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
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
// still render for real through this stub's plain default slot. Because the
// stub is a plain `<div>`, it also does not block clicks on the underlying
// table the way a real native `<dialog>`'s top-layer/backdrop would in a
// browser - this is what makes the "switching without closing" test below
// possible to exercise at all, even though a real user could never trigger
// it through the actually-rendered UI (the backdrop blocks the click).
const mountIndex = (props = {}) => mount(Index, {
    props: {
        categories: makeCategories(),
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

describe('Categories/Index.vue integration (real Data + Form children)', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders the categories table from the real Data child', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Electronics');
        expect(wrapper.text()).toContain('Furniture');
    });

    it('the Form starts in create mode (blank category_name) before any row is opened for edit', () => {
        const wrapper = mountIndex();

        const input = wrapper.find('input#category_name');
        expect(input.exists()).toBe(true);
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Category');
    });

    it('clicking a row\'s Edit action in Data actually opens Form pre-filled with that row\'s data', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        const input = wrapper.find('input#category_name');
        expect(input.element.value).toBe('Furniture');
        expect(wrapper.text()).toContain('Edit Category');
    });

    it('closing the modal after opening it for edit clears editingCategory state back to a blank create form', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        await nextTick();
        expect(wrapper.find('input#category_name').element.value).toBe('Electronics');

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');
        await nextTick();

        // Re-opening via "Create" afterwards must show a blank field, not
        // the previously-edited category's name leaking through.
        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Category');
        await createButton.trigger('click');
        await nextTick();

        const input = wrapper.find('input#category_name');
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Category');
    });

    it('switching from editing one category directly to another WITHOUT closing the modal in between does NOT resync the field - this is the real watch-on-"show" mechanism, not a :key-based remount like Roles/Permissions', async () => {
        // Index.vue deliberately never toggles `showModal` back to false
        // when a different row's Edit is clicked while the modal is already
        // open (`openEdit` only ever sets it to `true`, which is a no-op
        // when it's already `true`). Form.vue's sync is driven by a
        // `watch(() => props.show, ...)`, which only fires on an actual
        // value change - so without an intervening close, the field keeps
        // showing whatever was loaded for the first row. A real user can
        // never reach this state through the rendered UI (the modal's own
        // backdrop blocks clicks on the table underneath), but it is the
        // actual, documented behavior of this component as written.
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        await nextTick();
        expect(wrapper.find('input#category_name').element.value).toBe('Electronics');

        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('input#category_name').element.value).toBe('Electronics');

        // Closing and reopening for the second row does correctly resync,
        // since that is a real false-to-true transition of `show`.
        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');
        await nextTick();

        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('input#category_name').element.value).toBe('Furniture');
    });

    it('submitting the edit form from within Index posts through to CategoryController.update with the real category id', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await saveButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/categories/2', expect.objectContaining({ preserveScroll: true }));
    });
});
