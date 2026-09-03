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

const makePermissions = (overrides = {}) => ({
    data: [
        { id: 1, resource: 'ROLE', action: 'READ', name: 'ROLE:READ', roles_count: 2 },
        { id: 2, resource: 'ROLE', action: 'WRITE', name: 'ROLE:WRITE', roles_count: 1 },
    ],
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/permissions?page=1', label: '1', active: true },
        { url: null, label: 'Next &raquo;', active: false },
    ],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 2,
    from: 1,
    to: 2,
    ...overrides,
});

const mountData = (props = {}) => mount(Data, {
    props: {
        permissions: makePermissions(),
        search: '',
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Permissions/Partials/Data.vue', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('renders one row per permission with its full RESOURCE:ACTION name and role count', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('ROLE:READ');
        expect(rows[0].text()).toContain('2');
        expect(rows[1].text()).toContain('ROLE:WRITE');
        expect(rows[1].text()).toContain('1');
    });

    it('renders an empty-state row when there are no permissions', () => {
        const wrapper = mountData({ permissions: makePermissions({ data: [] }) });

        expect(wrapper.text()).toContain('No permissions found.');
    });

    it('emits "create" when the create-permission button is clicked', async () => {
        const wrapper = mountData();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('emits "edit" with the exact permission payload when a row\'s edit action is clicked', async () => {
        const wrapper = mountData();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        expect(wrapper.emitted('edit')).toHaveLength(1);
        expect(wrapper.emitted('edit')[0]).toEqual([
            { id: 2, resource: 'ROLE', action: 'WRITE', name: 'ROLE:WRITE', roles_count: 1 },
        ]);
    });

    it('runs a search against PermissionController.index with the current search text on enter', async () => {
        const wrapper = mountData();

        const input = wrapper.find('input[type="search"]');
        await input.setValue('read');
        await input.trigger('keyup.enter');

        expect(routerGetMock).toHaveBeenCalledWith('/permissions', { search: 'read' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('deletes a permission via router.delete only after the user confirms', async () => {
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/permissions/1', { preserveScroll: true });
    });

    it('does not call router.delete when the confirmation is dismissed', async () => {
        window.confirm.mockReturnValue(false);
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(routerDeleteMock).not.toHaveBeenCalled();
    });

    it('hides every write action (create, edit, delete) when the user lacks PERMISSION:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Permission');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });
});
