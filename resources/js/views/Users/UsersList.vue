<template>
  <div>
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <p class="mt-2 text-sm text-gray-700">
          Manage user roles and permissions assignments.
        </p>
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
                    Email
                  </th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Roles
                  </th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="loading && users.length === 0">
                  <td colspan="4" class="text-center py-4 text-sm text-gray-500">Loading...</td>
                </tr>
                <tr v-else-if="!loading && users.length === 0">
                  <td colspan="4" class="text-center py-4 text-sm text-gray-500">No users found</td>
                </tr>
                <tr v-for="user in users" :key="user.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                    {{ user.name }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ user.email }}
                  </td>
                  <td class="px-3 py-4 text-sm text-gray-500">
                    <span v-for="role in user.roles" :key="role.id" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mr-1">
                      {{ role.name }}
                    </span>
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <button @click="openManageDialog(user)" class="text-indigo-600 hover:text-indigo-900">
                      Manage
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Manage Dialog -->
    <div v-if="showDialog && currentUser" class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="closeDialog"></div>
        <div class="relative transform overflow-hidden rounded-lg bg-white shadow-xl sm:my-8 sm:w-full sm:max-w-lg">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <h3 class="text-lg font-medium mb-4">Manage: {{ currentUser.name }}</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                <div class="max-h-48 overflow-y-auto border rounded-md p-2">
                  <div v-for="role in availableRoles" :key="role.id" class="flex items-center mb-2">
                    <input
                      :id="`role-${role.id}`"
                      v-model="selectedRoles"
                      :value="role.id"
                      type="checkbox"
                      class="h-4 w-4 text-indigo-600 rounded"
                    />
                    <label :for="`role-${role.id}`" class="ml-2 text-sm text-gray-700">
                      {{ role.display_name || role.name }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              @click="saveUserRoles"
              :disabled="loading"
              class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-white sm:ml-3 sm:w-auto"
            >
              {{ loading ? 'Saving...' : 'Save' }}
            </button>
            <button @click="closeDialog" class="mt-3 inline-flex justify-center rounded-md border px-4 py-2 sm:mt-0 sm:w-auto">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useUsers } from '../../composables/useUsers';
import { useRoles } from '../../composables/useRoles';
import type { User, Role } from '../../types';

const { users, loading, fetchUsers, updateUserRoles } = useUsers();
const { fetchRoles } = useRoles();

const showDialog = ref(false);
const currentUser = ref<User | null>(null);
const availableRoles = ref<Role[]>([]);
const selectedRoles = ref<number[]>([]);

onMounted(async () => {
  await fetchUsers();
  const rolesData = await fetchRoles();
  availableRoles.value = rolesData.data;
});

function openManageDialog(user: User) {
  currentUser.value = user;
  selectedRoles.value = user.roles?.map(r => r.id) || [];
  showDialog.value = true;
}

function closeDialog() {
  showDialog.value = false;
  currentUser.value = null;
}

async function saveUserRoles() {
  if (!currentUser.value) return;
  try {
    await updateUserRoles(currentUser.value.id, selectedRoles.value);
    await fetchUsers();
    closeDialog();
  } catch (error) {
    console.error('Failed to update user roles:', error);
  }
}
</script>
