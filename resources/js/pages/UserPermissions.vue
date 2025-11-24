<script setup lang="ts">
import { ArrowLeft, Info, Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from "vue-sonner";

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';

interface Role {
    id: number;
    name: string;
    permission_ids: number[];
}

interface Permission {
    id: number;
    name: string;
}

interface RolePermission {
    permission: Permission;
    roles: string[];
}

const route = useRoute();
const router = useRouter();

const userId = route.params.id as string;
const userName = ref('');
const userEmail = ref('');
const roles = ref<Role[]>([]);
const permissions = ref<Permission[]>([]);
const selectedRoleIds = ref<number[]>([]);
const selectedPermissionIds = ref<number[]>([]);
const loading = ref(true);
const saving = ref(false);
const initialRoleIds = ref<number[]>([]);
const initialPermissionIds = ref<number[]>([]);
const initialRoleIdsSet = ref<Set<number>>(new Set());
const initialPermissionIdsSet = ref<Set<number>>(new Set());

// Computed: permissions granted by selected roles (deduplicated with role names)
const permissionsFromRoles = computed<RolePermission[]>(() => {
    const permMap = new Map<number, { permission: Permission; roles: string[] }>();

    for (const role of roles.value) {
        if (selectedRoleIds.value.includes(role.id)) {
            for (const permId of role.permission_ids) {
                const perm = permissions.value.find((p) => p.id === permId);
                if (perm) {
                    if (permMap.has(permId)) {
                        permMap.get(permId)!.roles.push(role.name);
                    } else {
                        permMap.set(permId, {
                            permission: perm,
                            roles: [role.name],
                        });
                    }
                }
            }
        }
    }

    return Array.from(permMap.values()).sort((a, b) => a.permission.name.localeCompare(b.permission.name));
});

// Computed: permission IDs from roles
const permissionIdsFromRoles = computed<Set<number>>(() => {
    return new Set(permissionsFromRoles.value.map((rp) => rp.permission.id));
});

// Computed: available permissions for direct assignment (excluding role permissions)
const availableDirectPermissions = computed<Permission[]>(() => {
    return permissions.value
        .filter((p) => !permissionIdsFromRoles.value.has(p.id))
        .sort((a, b) => a.name.localeCompare(b.name));
});

// Computed: check if there are any changes
const hasChanges = computed(() => {
    const currentRoles = [...selectedRoleIds.value].sort((a, b) => a - b);
    const currentPerms = [...selectedPermissionIds.value]
        .filter((id) => !permissionIdsFromRoles.value.has(id))
        .sort((a, b) => a - b);

    if (currentRoles.length !== initialRoleIds.value.length) return true;
    if (currentPerms.length !== initialPermissionIds.value.length) return true;

    for (let i = 0; i < currentRoles.length; i++) {
        if (currentRoles[i] !== initialRoleIds.value[i]) return true;
    }
    for (let i = 0; i < currentPerms.length; i++) {
        if (currentPerms[i] !== initialPermissionIds.value[i]) return true;
    }

    return false;
});

// Get role change type: 'added', 'removed', or null
const getRoleChangeType = (roleId: number): 'added' | 'removed' | null => {
    const wasSelected = initialRoleIdsSet.value.has(roleId);
    const isSelected = selectedRoleIds.value.includes(roleId);
    if (wasSelected === isSelected) return null;
    return isSelected ? 'added' : 'removed';
};

// Get permission change type: 'added', 'removed', or null
const getPermissionChangeType = (permissionId: number): 'added' | 'removed' | null => {
    const wasSelected = initialPermissionIdsSet.value.has(permissionId);
    const isSelected = selectedPermissionIds.value.includes(permissionId);
    if (wasSelected === isSelected) return null;
    return isSelected ? 'added' : 'removed';
};

const isRoleChecked = (roleId: number): boolean => selectedRoleIds.value.includes(roleId);

const isPermissionChecked = (permissionId: number): boolean => selectedPermissionIds.value.includes(permissionId);

const toggleRole = (roleId: number, checked: boolean) => {
    if (checked) {
        if (!selectedRoleIds.value.includes(roleId)) {
            selectedRoleIds.value = [...selectedRoleIds.value, roleId];
        }
    } else {
        selectedRoleIds.value = selectedRoleIds.value.filter((id) => id !== roleId);
    }
};

const togglePermission = (permissionId: number, checked: boolean) => {
    if (checked) {
        if (!selectedPermissionIds.value.includes(permissionId)) {
            selectedPermissionIds.value = [...selectedPermissionIds.value, permissionId];
        }
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((id) => id !== permissionId);
    }
};

const fetchUserPermissions = async () => {
    try {
        loading.value = true;

        const response = await axios(`/permissions-manager/api/users/${userId}/access`);
        const data = response.data;

        userName.value = data.user.name;
        userEmail.value = data.user.email;
        roles.value = data.roles;
        permissions.value = data.permissions;
        selectedRoleIds.value = data.user_role_ids;
        selectedPermissionIds.value = data.user_permission_ids;
        initialRoleIds.value = [...data.user_role_ids].sort((a, b) => a - b);
        initialPermissionIds.value = [...data.user_permission_ids].sort((a, b) => a - b);
        initialRoleIdsSet.value = new Set(data.user_role_ids);
        initialPermissionIdsSet.value = new Set(data.user_permission_ids);
    } catch (error) {
        console.error('Failed to fetch user permissions:', error);
    } finally {
        loading.value = false;
    }
};

const saveChanges = async () => {
    try {
        saving.value = true;

        // Filter out permissions that are now covered by roles
        const filteredPermissions = selectedPermissionIds.value.filter((id) => !permissionIdsFromRoles.value.has(id));

        await axios.put(`/permissions-manager/api/users/${userId}/access`, {
            roles: selectedRoleIds.value,
            permissions: filteredPermissions,
        });

        toast.success('User access updated successfully');
        router.push('/users');
    } catch (error) {
        toast.error('Failed to update user access');
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    fetchUserPermissions();
});
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="router.push('/users')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div>
                    <h1 class="text-2xl font-bold">Manage Access For {{ userName }}</h1>
                </div>
            </div>

            <Button @click="saveChanges" :disabled="saving || !hasChanges">
                <Save class="mr-2 h-4 w-4" />
                {{ saving ? 'Saving...' : 'Save Changes' }}
            </Button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading" class="space-y-6">
            <!-- Assign Roles Skeleton -->
            <div>
                <Skeleton class="mb-4 h-6 w-32" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <div v-for="i in 4" :key="i" class="flex items-center space-x-3 rounded-md border p-3">
                        <Skeleton class="h-5 w-5" />
                        <Skeleton class="h-4 w-24" />
                    </div>
                </div>
            </div>

            <!-- Permissions from Roles Skeleton -->
            <div>
                <Skeleton class="mb-4 h-6 w-64" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <div v-for="i in 6" :key="i" class="flex items-center justify-between rounded-md border p-3">
                        <Skeleton class="h-4 w-32" />
                        <Skeleton class="h-3 w-16" />
                    </div>
                </div>
            </div>

            <Separator class="my-4" />

            <!-- Direct Permissions Skeleton -->
            <div>
                <Skeleton class="mb-4 h-6 w-72" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <div v-for="i in 8" :key="i" class="flex items-center space-x-3 rounded-md border p-3">
                        <Skeleton class="h-5 w-5" />
                        <Skeleton class="h-4 w-28" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="space-y-6">
            <!-- Assign Roles -->
            <div>
                <h2 class="mb-4 text-lg font-semibold">Assign Roles</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex cursor-pointer items-center space-x-3 rounded-md border p-3 transition-colors"
                        :class="{
                            'border-success/30 bg-success/10': getRoleChangeType(role.id) === 'added',
                            'border-destructive/30 bg-destructive/10': getRoleChangeType(role.id) === 'removed',
                            'border-primary/30 bg-primary/10': !getRoleChangeType(role.id) && isRoleChecked(role.id),
                            'bg-background hover:bg-muted/50': !getRoleChangeType(role.id) && !isRoleChecked(role.id),
                        }"
                    >
                        <Checkbox
                            :modelValue="isRoleChecked(role.id)"
                            @update:modelValue="(checked) => toggleRole(role.id, checked)"
                        />
                        <span class="flex-1 text-sm leading-none font-medium"> {{ role.name }}</span>
                    </label>
                </div>
                <p v-if="roles.length === 0" class="mt-2 text-sm text-muted-foreground">
                    No roles available. Run sync:roles command.
                </p>
            </div>

            <!-- Permissions from Assigned Roles -->
            <div>
                <h2 class="mb-4 text-lg font-semibold">
                    Permissions from Assigned Roles
                    <span v-if="permissionsFromRoles.length">({{ permissionsFromRoles.length }})</span>
                </h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="item in permissionsFromRoles"
                        :key="item.permission.id"
                        class="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3"
                    >
                        <span class="text-sm font-medium"> {{ item.permission.name }}</span>
                        <span class="text-xs text-muted-foreground"> via {{ item.roles.join(', ') }}</span>
                    </div>
                </div>
                <div class="mt-3 flex items-start gap-2 text-sm text-muted-foreground">
                    <Info class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        These permissions are inherited from assigned roles and cannot be removed individually. Remove
                        the role to revoke these permissions.
                    </p>
                </div>
            </div>
            <Separator class="my-4" />
            <!-- Direct Permissions -->
            <div>
                <h2 class="mb-4 text-lg font-semibold">Direct Permissions (Override/Additional)</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <label
                        v-for="permission in availableDirectPermissions"
                        :key="permission.id"
                        class="flex cursor-pointer items-center space-x-3 rounded-md border p-3 transition-colors"
                        :class="{
                            'border-success/30 bg-success/10': getPermissionChangeType(permission.id) === 'added',
                            'border-destructive/30 bg-destructive/10':
                                getPermissionChangeType(permission.id) === 'removed',
                            'border-primary/30 bg-primary/10':
                                !getPermissionChangeType(permission.id) && isPermissionChecked(permission.id),
                            'bg-background hover:bg-muted/50':
                                !getPermissionChangeType(permission.id) && !isPermissionChecked(permission.id),
                        }"
                    >
                        <Checkbox
                            :modelValue="isPermissionChecked(permission.id)"
                            @update:modelValue="(checked) => togglePermission(permission.id, checked)"
                        />
                        <span class="flex-1 text-sm leading-none font-medium"> {{ permission.name }}</span>
                    </label>
                </div>
                <p v-if="availableDirectPermissions.length === 0" class="mt-2 text-sm text-muted-foreground">
                    {{
                        permissions.length === 0
                            ? 'No permissions available. Run sync:permissions command.'
                            : 'All permissions are already granted through roles.'
                    }}
                </p>
                <div
                    v-if="availableDirectPermissions.length > 0"
                    class="mt-3 flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <Info class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>Direct permissions are assigned specifically to this user and are independent of their roles.</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 border-t pt-4">
                <Button @click="saveChanges" :disabled="saving || !hasChanges">
                    <Save class="mr-2 h-4 w-4" />
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>
    </div>
</template>
