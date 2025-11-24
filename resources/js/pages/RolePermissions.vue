<script setup lang="ts">
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';

interface ModelPermission {
    action: string;
    permission_id: number;
    assigned: boolean;
}

interface AvailablePermissions {
    [model: string]: ModelPermission[];
}

const route = useRoute();
const router = useRouter();

const roleId = route.params.id as string;
const roleName = ref('');
const models = ref<string[]>([]);
const availablePermissions = ref<AvailablePermissions>({});
const loading = ref(true);
const saving = ref(false);
const openModel = ref<string | null>(null);
const loadError = ref(false);
const initialPermissionIds = ref<number[]>([]);
const initialPermissionState = ref<Map<number, boolean>>(new Map());

const selectAll = () => {
    for (const model of models.value) {
        const permissions = availablePermissions.value[model] || [];
        for (const permission of permissions) {
            permission.assigned = true;
        }
    }
};

const deselectAll = () => {
    for (const model of models.value) {
        const permissions = availablePermissions.value[model] || [];
        for (const permission of permissions) {
            permission.assigned = false;
        }
    }
};

const isAllSelected = computed(() => {
    let total = 0;
    for (const model of models.value) {
        const permissions = availablePermissions.value[model] || [];
        for (const permission of permissions) {
            total++;
            if (!permission.assigned) {
                return false;
            }
        }
    }
    return total > 0;
});

const hasAnySelected = computed(() => {
    for (const model of models.value) {
        const permissions = availablePermissions.value[model] || [];
        for (const permission of permissions) {
            if (permission.assigned) {
                return true;
            }
        }
    }
    return false;
});

const hasChanges = computed(() => {
    const currentIds: number[] = [];
    for (const model of models.value) {
        const permissions = availablePermissions.value[model] || [];
        for (const permission of permissions) {
            if (permission.assigned) {
                currentIds.push(permission.permission_id);
            }
        }
    }
    currentIds.sort((a, b) => a - b);

    if (currentIds.length !== initialPermissionIds.value.length) {
        return true;
    }

    for (let i = 0; i < currentIds.length; i++) {
        if (currentIds[i] !== initialPermissionIds.value[i]) {
            return true;
        }
    }

    return false;
});

const hasNoPermissions = computed(() => {
    return models.value.length === 0;
});

// Permission counts for each model
const getModelPermissionCount = (model: string) => {
    const permissions = availablePermissions.value[model] || [];
    const selected = permissions.filter((p) => p.assigned).length;
    return { selected, total: permissions.length };
};

// Get permission change type: 'added', 'removed', or null
const getPermissionChangeType = (permissionId: number, currentAssigned: boolean): 'added' | 'removed' | null => {
    const initial = initialPermissionState.value.get(permissionId);
    if (initial === currentAssigned) return null;
    return currentAssigned ? 'added' : 'removed';
};

// Check if model has any selected
const hasModelAnySelected = (model: string) => {
    const permissions = availablePermissions.value[model] || [];
    return permissions.some((p) => p.assigned);
};

const fetchRolePermissions = async () => {
    try {
        loading.value = true;
        loadError.value = false;

        const response = await axios.get(`/permissions-manager/api/roles/${roleId}/permissions`);
        const data = response.data;

        roleName.value = data.role.name;
        models.value = data.models;
        availablePermissions.value = data.available_permissions;

        // Store initial permission IDs for change detection
        const ids: number[] = [];
        const stateMap = new Map<number, boolean>();
        for (const model of data.models) {
            const permissions = data.available_permissions[model] || [];
            for (const permission of permissions) {
                stateMap.set(permission.permission_id, permission.assigned);
                if (permission.assigned) {
                    ids.push(permission.permission_id);
                }
            }
        }
        initialPermissionIds.value = ids.sort((a, b) => a - b);
        initialPermissionState.value = stateMap;

        // Open first model by default
        if (data.models.length > 0) {
            openModel.value = data.models[0];
        }
    } catch (error) {
        loadError.value = true;
        toast.error('Failed to load role permissions');
    } finally {
        loading.value = false;
    }
};

const savePermissions = async () => {
    try {
        saving.value = true;
        const permissionIds: number[] = [];
        for (const model of models.value) {
            const permissions = availablePermissions.value[model] || [];
            for (const permission of permissions) {
                if (permission.assigned) {
                    permissionIds.push(permission.permission_id);
                }
            }
        }
        await axios.put(`/permissions-manager/api/roles/${roleId}/permissions`, {
            permissions: permissionIds,
        });
        toast.success('Permissions saved successfully');
        router.push('/roles');
    } catch (error) {
        toast.error('Failed to save permissions');
    } finally {
        saving.value = false;
    }
};

const toggleModelSelect = (model: string) => {
    const permissions = availablePermissions.value[model];
    const allSelected = permissions.every((p) => p.assigned);

    for (const permission of permissions) {
        permission.assigned = !allSelected;
    }

    console.log(permissions);
};

const isModelFullySelected = (model: string) => {
    const permissions = availablePermissions.value[model];
    return permissions.every((p) => p.assigned);
};

const toggleGlobalSelect = (checked: boolean) => {
    if (checked) {
        selectAll();
    } else {
        deselectAll();
    }
};

onMounted(() => {
    fetchRolePermissions();
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="router.push('/roles')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
                <h1 class="text-2xl font-bold">{{ roleName }}</h1>
            </div>

            <Button v-if="!hasNoPermissions" @click="savePermissions" :disabled="saving || !hasChanges">
                <Save class="mr-2 h-4 w-4" />
                {{ saving ? 'Saving...' : 'Save Permissions' }}
            </Button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading" class="space-y-6">
            <div class="flex items-center gap-3">
                <Skeleton class="h-5 w-5" />
                <Skeleton class="h-6 w-48" />
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 xl:grid-cols-4">
                <div v-for="i in 4" :key="i" class="max-w-lg overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Skeleton class="h-6 w-24" />
                            <Skeleton class="h-4 w-12" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Skeleton class="h-5 w-5" />
                            <Skeleton class="h-4 w-16" />
                        </div>
                    </div>
                    <div class="border-t"></div>
                    <div class="space-y-3 px-4 py-4">
                        <div v-for="j in 5" :key="j" class="flex items-center gap-3">
                            <Skeleton class="h-5 w-5" />
                            <Skeleton class="h-4 w-20" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="loadError" class="py-8 text-center">
            <p class="mb-4 text-destructive">Failed to load permissions</p>
            <Button variant="outline" @click="fetchRolePermissions">Retry</Button>
        </div>

        <div v-else-if="hasNoPermissions" class="py-8 text-center">
            <p class="mb-2 text-muted-foreground">No permissions available</p>
        </div>

        <!-- MAIN PERMISSIONS UI -->
        <div v-else class="space-y-6">
            <!-- Global Select/Deselect All -->
            <div class="flex items-center gap-3">
                <Checkbox
                    :modelValue="isAllSelected ? true : hasAnySelected ? 'indeterminate' : false"
                    @update:modelValue="toggleGlobalSelect"
                />
                <label class="text-lg font-medium">Select / Deselect All</label>
            </div>

            <!-- Responsive grid of cards -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="model in models"
                    :key="model"
                    class="max-w-lg overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <!-- Card Header -->
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold capitalize">{{ model }}</h2>
                            <span class="text-sm text-muted-foreground">
                                ({{ getModelPermissionCount(model).selected }}/{{
                                    getModelPermissionCount(model).total
                                }})
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox
                                :id="`select-all-${model}`"
                                :modelValue="
                                    isModelFullySelected(model)
                                        ? true
                                        : hasModelAnySelected(model)
                                          ? 'indeterminate'
                                          : false
                                "
                                @update:modelValue="() => toggleModelSelect(model)"
                            />
                            <label :for="`select-all-${model}`" class="cursor-pointer text-sm hover:text-primary">
                                {{ isModelFullySelected(model) ? 'Deselect All' : 'Select All' }}
                            </label>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="border-t"></div>

                    <!-- Permissions list -->
                    <div class="space-y-3 px-4 py-4">
                        <div
                            v-for="permission in availablePermissions[model]"
                            :key="permission.permission_id"
                            class="flex items-center gap-3 rounded px-2 py-1"
                            :class="{
                                'border border-success/30 bg-success/10':
                                    getPermissionChangeType(permission.permission_id, permission.assigned) === 'added',
                                'border border-destructive/30 bg-destructive/10':
                                    getPermissionChangeType(permission.permission_id, permission.assigned) ===
                                    'removed',
                            }"
                        >
                            <Checkbox v-model="permission.assigned" :id="`permission-${permission.permission_id}`" />
                            <label :for="`permission-${permission.permission_id}`" class="cursor-pointer text-sm">
                                {{ permission.action }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
