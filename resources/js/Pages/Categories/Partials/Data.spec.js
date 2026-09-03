import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGetMock, routerDeleteMock, canMock } = vi.hoisted(() => ({
    routerGetMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: routerGetMock,
        delete: routerDeleteMock,
    },
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

import Data from './Data.vue';

const makeCategories = (overrides = {}) => ({
    data: [
        { id: 1, category_name: 'Electronics', created_at: '2026-01-01T00:00:00.000000Z' },
        { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
    ],
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/categories?page=1', label: '1', active: true },
        { url: '/categories?page=2', label: '2', active: false },
        { url: null, label: 'Next &raquo;', active: false },
    ],
    current_page: 1,
    last_page: 2,
    per_page: 10,
    total: 7,
    from: 1,
    to: 2,
    ...overrides,
});

const mountData = (props = {}) => mount(Data, {
    props: {
        categories: makeCategories(),
        search: '',
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Categories/Partials/Data.vue', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('renders one row per category with its name', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Electronics');
        expect(rows[1].text()).toContain('Furniture');
    });

    it('renders an empty-state row when there are no categories', () => {
        const wrapper = mountData({ categories: makeCategories({ data: [] }) });

        expect(wrapper.text()).toContain('No categories found.');
    });

    it('emits "create" when the create-category button is clicked', async () => {
        const wrapper = mountData();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('emits "edit" with the exact category payload when a row\'s edit action is clicked', async () => {
        const wrapper = mountData();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        expect(wrapper.emitted('edit')).toHaveLength(1);
        expect(wrapper.emitted('edit')[0]).toEqual([
            { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
        ]);
    });

    it('runs a search against CategoryController.index with the current search text on enter', async () => {
        const wrapper = mountData({ search: 'elec' });

        const input = wrapper.find('input[type="search"]');
        expect(input.element.value).toBe('elec');

        await input.setValue('electronics');
        await input.trigger('keyup.enter');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock).toHaveBeenCalledWith('/categories', { search: 'electronics' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('also runs a search on blur', async () => {
        const wrapper = mountData();

        const input = wrapper.find('input[type="search"]');
        await input.setValue('furn');
        await input.trigger('blur');

        expect(routerGetMock).toHaveBeenCalledWith('/categories', { search: 'furn' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('deletes a category via router.delete only after the user confirms', async () => {
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/categories/1', { preserveScroll: true });
    });

    it('does not call router.delete when the confirmation is dismissed', async () => {
        window.confirm.mockReturnValue(false);
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(routerDeleteMock).not.toHaveBeenCalled();
    });

    it('renders a clickable link for pagination entries that have a url, and plain text for the ones that do not', () => {
        const wrapper = mountData();

        const pagination = wrapper.find('.mt-4');
        const links = pagination.findAll('a');
        expect(links).toHaveLength(2);
        expect(wrapper.text()).toContain('Previous');
        expect(wrapper.text()).toContain('Next');
    });

    it('hides every write action (create, edit, delete) when the user lacks CATEGORY:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Category');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });
});
