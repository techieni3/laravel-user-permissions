<template>
  <div>
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <p class="mt-2 text-sm text-gray-700">
          A list of all permissions in your application.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button
          @click="openCreateDialog"
          class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
        >
          Add Permission
        </button>
      </div>
    </div>

    <div class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                    Name
                  </th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Display Name
                  </th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading && permissions.length === 0">
                  <td colspan="3" class="text-center py-4 text-sm text-gray-500">Loading...</td>
                </tr>
                <tr v-else-if="!loading && permissions.length === 0">
                  <td colspan="3" class="text-center py-4 text-sm text-gray-500">No permissions found</td>
                </tr>
                <tr v-for="permission in permissions" :key="permission.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                    {{ permission.name }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ permission.display_name || '-' }}
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <button @click="openEditDialog(permission)" class="text-indigo-600 hover:text-indigo-900 mr-4">
                      Edit
                    </button>
                    <button @click="confirmDelete(permission)" class="text-red-600 hover:text-red-900">
                      Delete
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Dialog -->
    <div v-if="showDialog" class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="closeDialog"></div>
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg">
          <form @submit.prevent="savePermission">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
              <h3 class="text-lg font-medium mb-4">
                {{ editingPermission ? 'Edit Permission' : 'Create Permission' }}
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    v-model="formData.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Display Name</label>
                  <input
                    v-model="formData.display_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 border"
                  />
                </div>
              </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
              <button type="submit" :disabled="loading" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-white sm:ml-3 sm:w-auto">
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
              <button type="button" @click="closeDialog" class="mt-3 inline-flex w-full justify-center rounded-md border px-4 py-2 sm:mt-0 sm:w-auto">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePermissions } from '../../composables/usePermissions';
import type { Permission } from '../../types';

const { permissions, loading, fetchPermissions, createPermission, updatePermission, deletePermission } = usePermissions();

const showDialog = ref(false);
const editingPermission = ref<Permission | null>(null);
const formData = ref({ name: '', display_name: '' });

onMounted(() => fetchPermissions());

function openCreateDialog() {
  editingPermission.value = null;
  formData.value = { name: '', display_name: '' };
  showDialog.value = true;
}

function openEditDialog(permission: Permission) {
  editingPermission.value = permission;
  formData.value = {
    name: permission.name,
    display_name: permission.display_name || '',
  };
  showDialog.value = true;
}

function closeDialog() {
  showDialog.value = false;
}

async function savePermission() {
  try {
    if (editingPermission.value) {
      await updatePermission(editingPermission.value.id, formData.value);
    } else {
      await createPermission(formData.value);
    }
    await fetchPermissions();
    closeDialog();
  } catch (error) {
    console.error('Failed to save permission:', error);
  }
}

async function confirmDelete(permission: Permission) {
  if (confirm(`Delete permission "${permission.name}"?`)) {
    try {
      await deletePermission(permission.id);
      await fetchPermissions();
    } catch (error) {
      console.error('Failed to delete permission:', error);
    }
  }
}
</script>
