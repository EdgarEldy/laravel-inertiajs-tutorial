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
    trans: (key) => key,
}));

vi.mock('@/lib/can', () => ({
    can: canMock,
}));

import Data from './Data.vue';

const makeRoles = (overrides = {}) => ({
    data: [
        { id: 1, role_name: 'ADMIN', users_count: 2 },
        { id: 2, role_name: 'USER', users_count: 5 },
    ],
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/roles?page=1', label: '1', active: true },
        { url: '/roles?page=2', label: '2', active: false },
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
        roles: makeRoles(),
        search: '',
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Roles/Partials/Data.vue', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('renders one row per role with its name and user count', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('ADMIN');
        expect(rows[0].text()).toContain('2');
        expect(rows[1].text()).toContain('USER');
        expect(rows[1].text()).toContain('5');
    });

    it('renders an empty-state row when there are no roles', () => {
        const wrapper = mountData({ roles: makeRoles({ data: [] }) });

        expect(wrapper.text()).toContain('No roles found.');
    });

    it('emits "create" when the create-role button is clicked', async () => {
        const wrapper = mountData();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('emits "edit" with the exact role payload when a row\'s edit action is clicked', async () => {
        const wrapper = mountData();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');

        expect(wrapper.emitted('edit')).toHaveLength(1);
        expect(wrapper.emitted('edit')[0]).toEqual([{ id: 1, role_name: 'ADMIN', users_count: 2 }]);
    });

    it('runs a search against RoleController.index with the current search text on enter', async () => {
        const wrapper = mountData({ search: 'adm' });

        const input = wrapper.find('input[type="search"]');
        expect(input.element.value).toBe('adm');

        await input.setValue('admin');
        await input.trigger('keyup.enter');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock).toHaveBeenCalledWith('/roles', { search: 'admin' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('also runs a search on blur', async () => {
        const wrapper = mountData();

        const input = wrapper.find('input[type="search"]');
        await input.setValue('use');
        await input.trigger('blur');

        expect(routerGetMock).toHaveBeenCalledWith('/roles', { search: 'use' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('deletes a role via router.delete only after the user confirms', async () => {
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/roles/1', { preserveScroll: true });
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

        // Scoped to the pagination row specifically - the table itself also
        // renders a "Permissions" <a> per row (via the same Link stub),
        // which would otherwise inflate this count.
        const pagination = wrapper.find('.mt-4');
        const links = pagination.findAll('a');
        // Only the two page links ("1" and "2") have a url - "Previous" and
        // "Next" are both disabled (url: null) on a first/last page combo
        // that never actually happens together here, but the fixture still
        // proves the disabled ones render as plain, non-clickable text.
        expect(links).toHaveLength(2);
        expect(wrapper.text()).toContain('Previous');
        expect(wrapper.text()).toContain('Next');
    });

    it('hides every write action (create, edit, delete, permissions link) when the user lacks ROLE:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Role');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
        expect(wrapper.text()).not.toContain('Permissions');
    });
});
