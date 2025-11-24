<script setup lang="ts">
import type { ColumnDef, ColumnFiltersState, SortingState } from '@tanstack/vue-table';

import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { ArrowUpDown, Settings } from 'lucide-vue-next';
import { h, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';

export interface Role {
    id: number;
    name: string;
    updated_at: string;
    permissions_count: number;
}

const roles = ref<Role[]>([]);
const loading = ref(true);

const columns: ColumnDef<Role>[] = [
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
        accessorKey: 'permissions_count',
        header: () => h('div', {}, 'Permissions'),
        cell: ({ row }) => {
            const count = row.getValue('permissions_count') as number;
            return h('div', { class: 'font-medium' }, count);
        },
    },
    {
        accessorKey: 'updated_at',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Updated At', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            );
        },
        cell: ({ row }) => {
            return h('div', {}, row.getValue('updated_at'));
        },
    },
    {
        id: 'actions',
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const role = row.original;
            return h(
                'div',
                {},
                h(RouterLink, { to: `/roles/${role.id}/permissions` }, () =>
                    h(Button, { variant: 'outline', size: 'sm' }, () => [
                        h(Settings, { class: 'h-4 w-4 mr-2' }),
                        'Manage Permissions',
                    ]),
                ),
            );
        },
    },
];

const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return roles.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    onColumnFiltersChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnFilters),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
    },
});

const fetchRoles = async () => {
    try {
        loading.value = true;
        const response = await axios('/permissions-manager/api/roles');
        roles.value = response.data?.data;
    } catch (error) {
        console.error('Failed to fetch roles:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchRoles();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Roles</CardTitle>
        </CardHeader>
        <CardContent>
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
                            <TableCell :colspan="columns.length" class="h-24 text-center">No roles found.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
