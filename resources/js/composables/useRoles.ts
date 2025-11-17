import { ref, type Ref } from 'vue';
import api from '../lib/api';
import type { Role, PaginatedResponse, Permission } from '../types';

export function useRoles() {
    const roles: Ref<Role[]> = ref([]);
    const loading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    async function fetchRoles(page = 1, perPage = 15) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get<PaginatedResponse<Role>>('/roles', {
                params: { page, per_page: perPage },
            });
            roles.value = response.data.data;
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch roles';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createRole(data: { name: string; display_name?: string; permissions?: number[] }) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.post<Role>('/roles', data);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to create role';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateRole(id: number, data: { name: string; display_name?: string; permissions?: number[] }) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.put<Role>(`/roles/${id}`, data);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update role';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteRole(id: number) {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`/roles/${id}`);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete role';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchPermissions(): Promise<Permission[]> {
        try {
            const response = await api.get<Permission[]>('/role-permissions');
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch permissions';
            throw err;
        }
    }

    return {
        roles,
        loading,
        error,
        fetchRoles,
        createRole,
        updateRole,
        deleteRole,
        fetchPermissions,
    };
}
