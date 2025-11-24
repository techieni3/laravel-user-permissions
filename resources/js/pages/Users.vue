<script setup lang="ts">
import type { ColumnDef, SortingState } from '@tanstack/vue-table';

import { FlexRender, getCoreRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { ArrowUpDown, ChevronLeft, ChevronRight, Settings } from 'lucide-vue-next';
import { h, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';

interface Role {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    roles: Role[];
    permissions_count: number;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

const users = ref<User[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const searchTimeout = ref<number | null>(null);
const paginationMeta = ref<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
});
const paginationLinks = ref<PaginationLinks>({
    first: null,
    last: null,
    prev: null,
    next: null,
});

const columns: ColumnDef<User>[] = [
    {
        accessorKey: 'name',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => h('div', { class: 'ml-2 font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'roles',
        header: () => h('div', {}, 'Roles'),
        cell: ({ row }) => {
            const roles = row.getValue('roles') as Role[];
            if (!roles || roles.length === 0) {
                return h('span', { class: 'text-muted-foreground text-sm' }, 'No roles');
            }
            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                roles.map((role) => h(Badge, { variant: 'secondary', key: role.id }, () => role.name)),
            );
        },
    },
    {
        accessorKey: 'permissions_count',
        header: () => h('div', {}, 'Direct Permissions'),
        cell: ({ row }) => {
            const count = row.getValue('permissions_count') as number;
            return h('div', { class: 'font-medium' }, count);
        },
    },
    {
        id: 'actions',
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const user = row.original;
            return h(
                'div',
                {},
                h(RouterLink, { to: `/users/${user.id}/permissions` }, () =>
                    h(Button, { variant: 'outline', size: 'sm' }, () => [
                        h(Settings, { class: 'h-4 w-4 mr-2' }),
                        'Manage Access',
                    ]),
                ),
            );
        },
    },
];

const sorting = ref<SortingState>([]);

const table = useVueTable({
    get data() {
        return users.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    state: {
        get sorting() {
            return sorting.value;
        },
    },
});

const fetchUsers = async (url?: string) => {
    try {
        loading.value = true;
        let endpoint = url || '/permissions-manager/api/users';

        // Add search query parameter if searching and no URL provided
        if (!url && searchQuery.value) {
            const params = new URLSearchParams({ search: searchQuery.value });
            endpoint = `${endpoint}?${params.toString()}`;
        }

        const response = await axios(endpoint);
        users.value = response.data?.data || [];
        paginationMeta.value = {
            current_page: response.data?.current_page || 1,
            last_page: response.data?.last_page || 1,
            per_page: response.data?.per_page || 50,
            total: response.data?.total || 0,
        };
        paginationLinks.value = {
            first: response.data?.first_page_url || null,
            last: response.data?.last_page_url || null,
            prev: response.data?.prev_page_url || null,
            next: response.data?.next_page_url || null,
        };
    } catch (error) {
        console.error('Failed to fetch users:', error);
    } finally {
        loading.value = false;
    }
};

const goToPage = (url: string | null) => {
    if (url) {
        fetchUsers(url);
    }
};

// Watch for search query changes with debounce
watch(searchQuery, () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = window.setTimeout(() => {
        fetchUsers();
    }, 300); // 300ms debounce
});

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Users</h1>
        </div>
        <div class="flex items-center py-4">
            <Input class="max-w-sm" placeholder="Search users..." v-model="searchQuery" />
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="loading">
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center">Loading...</TableCell>
                        </TableRow>
                    </template>
                    <template v-else-if="table.getRowModel().rows?.length">
                        <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center">No users found.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-between py-4">
            <div class="text-sm text-muted-foreground">
                Showing
                {{ (paginationMeta.current_page - 1) * paginationMeta.per_page + 1 }}
                to
                {{ Math.min(paginationMeta.current_page * paginationMeta.per_page, paginationMeta.total) }}
                of {{ paginationMeta.total }}users
            </div>
            <div class="flex items-center space-x-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!paginationLinks.prev"
                    @click="goToPage(paginationLinks.prev)"
                >
                    <ChevronLeft class="h-4 w-4" />
                    Previous
                </Button>
                <span class="text-sm">
                    Page {{ paginationMeta.current_page }}of
                    {{ paginationMeta.last_page }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!paginationLinks.next"
                    @click="goToPage(paginationLinks.next)"
                >
                    Next
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
