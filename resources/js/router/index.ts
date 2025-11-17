import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('../views/Dashboard.vue'),
        meta: { title: 'Dashboard' },
    },
    {
        path: '/roles',
        name: 'roles',
        component: () => import('../views/Roles/RolesList.vue'),
        meta: { title: 'Roles' },
    },
    {
        path: '/permissions',
        name: 'permissions',
        component: () => import('../views/Permissions/PermissionsList.vue'),
        meta: { title: 'Permissions' },
    },
    {
        path: '/users',
        name: 'users',
        component: () => import('../views/Users/UsersList.vue'),
        meta: { title: 'Users' },
    },
];

export default routes;
