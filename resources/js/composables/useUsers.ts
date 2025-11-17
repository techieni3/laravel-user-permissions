import { ref, type Ref } from 'vue';
import api from '../lib/api';
import type { User, PaginatedResponse } from '../types';

export function useUsers() {
    const users: Ref<User[]> = ref([]);
    const loading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    async function fetchUsers(page = 1, perPage = 15, search = '') {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get<PaginatedResponse<User>>('/users', {
                params: { page, per_page: perPage, search },
            });
            users.value = response.data.data;
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch users';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateUserRoles(userId: number, roleIds: number[]) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.put<User>(`/users/${userId}/roles`, {
                roles: roleIds,
            });
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update user roles';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateUserPermissions(userId: number, permissionIds: number[]) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.put<User>(`/users/${userId}/permissions`, {
                permissions: permissionIds,
            });
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update user permissions';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        users,
        loading,
        error,
        fetchUsers,
        updateUserRoles,
        updateUserPermissions,
    };
}
