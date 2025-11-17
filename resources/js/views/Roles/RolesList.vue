<template>
  <div>
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <p class="mt-2 text-sm text-gray-700">
          A list of all roles in your application including their name, display name, and assigned permissions.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button
          type="button"
          @click="openCreateDialog"
          class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
        >
          Add Role
        </button>
      </div>
    </div>

    <!-- Roles Table -->
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
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Permissions
                  </th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Users
                  </th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading && roles.length === 0">
                  <td colspan="5" class="text-center py-4 text-sm text-gray-500">Loading...</td>
                </tr>
                <tr v-else-if="!loading && roles.length === 0">
                  <td colspan="5" class="text-center py-4 text-sm text-gray-500">No roles found</td>
                </tr>
                <tr v-for="role in roles" :key="role.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                    {{ role.name }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ role.display_name || '-' }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ role.permissions?.length || 0 }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ role.users_count || 0 }}
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <button
                      @click="openEditDialog(role)"
                      class="text-indigo-600 hover:text-indigo-900 mr-4"
                    >
                      Edit
                    </button>
                    <button
                      @click="confirmDelete(role)"
                      class="text-red-600 hover:text-red-900"
                    >
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

    <!-- Create/Edit Dialog -->
    <div v-if="showDialog" class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDialog"></div>
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
          <form @submit.prevent="saveRole">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ editingRole ? 'Edit Role' : 'Create Role' }}
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    v-model="formData.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Display Name</label>
                  <input
                    v-model="formData.display_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                  <div class="max-h-48 overflow-y-auto border border-gray-300 rounded-md p-2">
                    <div v-for="permission in availablePermissions" :key="permission.id" class="flex items-center mb-2">
                      <input
                        :id="`perm-${permission.id}`"
                        v-model="formData.permissions"
                        :value="permission.id"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                      />
                      <label :for="`perm-${permission.id}`" class="ml-2 text-sm text-gray-700">
                        {{ permission.display_name || permission.name }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
              <button
                type="submit"
                :disabled="loading"
                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
              <button
                type="button"
                @click="closeDialog"
                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
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
import { useRoles } from '../../composables/useRoles';
import type { Role, Permission } from '../../types';

const { roles, loading, fetchRoles, createRole, updateRole, deleteRole, fetchPermissions } = useRoles();

const showDialog = ref(false);
const editingRole = ref<Role | null>(null);
const availablePermissions = ref<Permission[]>([]);
const formData = ref({
  name: '',
  display_name: '',
  permissions: [] as number[],
});

onMounted(async () => {
  await loadData();
});

async function loadData() {
  await fetchRoles();
  availablePermissions.value = await fetchPermissions();
}

function openCreateDialog() {
  editingRole.value = null;
  formData.value = {
    name: '',
    display_name: '',
    permissions: [],
  };
  showDialog.value = true;
}

function openEditDialog(role: Role) {
  editingRole.value = role;
  formData.value = {
    name: role.name,
    display_name: role.display_name || '',
    permissions: role.permissions?.map((p) => p.id) || [],
  };
  showDialog.value = true;
}

function closeDialog() {
  showDialog.value = false;
  editingRole.value = null;
}

async function saveRole() {
  try {
    if (editingRole.value) {
      await updateRole(editingRole.value.id, formData.value);
    } else {
      await createRole(formData.value);
    }
    await loadData();
    closeDialog();
  } catch (error) {
    console.error('Failed to save role:', error);
  }
}

async function confirmDelete(role: Role) {
  if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
    try {
      await deleteRole(role.id);
      await loadData();
    } catch (error) {
      console.error('Failed to delete role:', error);
    }
  }
}
</script>
