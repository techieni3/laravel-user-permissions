import { ref, type Ref } from 'vue';
import api from '../lib/api';
import type { Permission, PaginatedResponse } from '../types';

export function usePermissions() {
    const permissions: Ref<Permission[]> = ref([]);
    const loading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    async function fetchPermissions(page = 1, perPage = 15, search = '') {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get<PaginatedResponse<Permission>>('/permissions', {
                params: { page, per_page: perPage, search },
            });
            permissions.value = response.data.data;
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch permissions';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createPermission(data: { name: string; display_name?: string }) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.post<Permission>('/permissions', data);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to create permission';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updatePermission(id: number, data: { name: string; display_name?: string }) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.put<Permission>(`/permissions/${id}`, data);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update permission';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deletePermission(id: number) {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`/permissions/${id}`);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete permission';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        permissions,
        loading,
        error,
        fetchPermissions,
        createPermission,
        updatePermission,
        deletePermission,
    };
}
