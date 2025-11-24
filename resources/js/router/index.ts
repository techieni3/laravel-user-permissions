import RolePermissions from '@/pages/RolePermissions.vue';
import Roles from '@/pages/Roles.vue';
import UserPermissions from '@/pages/UserPermissions.vue';
import Users from '@/pages/Users.vue';

export const routes = [
  { path: '/', redirect: '/roles' },
  {
    path: '/roles',
    name: 'roles',
    component: Roles,
  },
  {
    path: '/roles/:id/permissions',
    name: 'role-permissions',
    component: RolePermissions,
  },
  {
    path: '/users',
    name: 'users',
    component: Users,
  },
  {
    path: '/users/:id/permissions',
    name: 'user-permissions',
    component: UserPermissions,
  },
];
