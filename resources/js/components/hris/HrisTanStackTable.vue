<script setup lang="ts" generic="TData">
import type {
    Cell,
    Header,
    Table as TanstackTable,
} from '@tanstack/table-core';
import { FlexRender } from '@tanstack/vue-table';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

type ColumnChromeMeta = {
    headClass?: string;
    cellClass?: string;
};

const props = withDefaults(
    defineProps<{
        table: TanstackTable<TData>;
        emptyMessage: string;
        headerRowClass?: string;
        /** Passed to underlying shadcn `Table` wrapper (default there is `overflow-auto`). */
        tableWrapperClass?: string;
    }>(),
    {
        headerRowClass:
            '[&_tr]:bg-muted/50 [&_tr]:border-b [&_tr]:border-border/60',
    },
);

function headClassFor(header: Header<TData, unknown>): string | undefined {
    const meta = header.column.columnDef.meta as ColumnChromeMeta | undefined;

    return meta?.headClass;
}

function cellClassFor(cell: Cell<TData, unknown>): string | undefined {
    const meta = cell.column.columnDef.meta as ColumnChromeMeta | undefined;

    return meta?.cellClass;
}
</script>

<template>
    <div class="w-full">
        <Table :wrapper-class="props.tableWrapperClass">
            <TableHeader :class="props.headerRowClass">
                <TableRow
                    v-for="headerGroup in table.getHeaderGroups()"
                    :key="headerGroup.id"
                >
                    <TableHead
                        v-for="header in headerGroup.headers"
                        :key="header.id"
                        :class="cn(headClassFor(header))"
                    >
                        <FlexRender
                            v-if="!header.isPlaceholder"
                            :render="header.column.columnDef.header"
                            :props="header.getContext()"
                        />
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="table.getRowModel().rows?.length">
                    <TableRow
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                    >
                        <TableCell
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                            :class="cn(cellClassFor(cell))"
                        >
                            <FlexRender
                                :render="cell.column.columnDef.cell"
                                :props="cell.getContext()"
                            />
                        </TableCell>
                    </TableRow>
                </template>
                <TableRow v-else>
                    <TableCell
                        :colspan="table.getAllLeafColumns().length"
                        class="h-24 text-center text-muted-foreground"
                    >
                        {{ emptyMessage }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
