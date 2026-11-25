<script setup lang="ts" generic="T extends object">
import RecordMetadata from '@/components/RecordMetadata.vue';
defineProps<{
    rows: T[];
    columns: { key: string; label: string; width?: string }[];
    caption: string;
    rowKey?: keyof T;
    wide?: boolean;
    empty?: string;
}>();
const value = (row: T, key: string) => row[key as keyof T] ?? '—';
const creation = (row: T) => {
    const fields = row as Record<string, unknown>;
    return {
        createdAt:
            typeof fields.createdAt === 'string' ? fields.createdAt : null,
        createdBy:
            typeof fields.createdBy === 'string' ? fields.createdBy : null,
        updatedAt:
            typeof fields.updatedAt === 'string' ? fields.updatedAt : null,
        updatedBy:
            typeof fields.updatedBy === 'string' ? fields.updatedBy : null,
    };
};
</script>
<template>
    <div class="@container overflow-hidden rounded-xl border bg-card">
        <p
            v-if="!rows.length"
            class="p-8 text-center text-sm text-muted-foreground"
        >
            {{ empty || 'No records to show.' }}
        </p>
        <table
            v-else
            role="table"
            class="block w-full table-fixed text-left text-sm"
            :class="wide ? '@min-[64rem]:table' : '@min-[48rem]:table'"
        >
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead
                role="rowgroup"
                class="sr-only border-b bg-muted/40 text-xs text-muted-foreground"
                :class="
                    wide
                        ? '@min-[64rem]:not-sr-only @min-[64rem]:table-header-group'
                        : '@min-[48rem]:not-sr-only @min-[48rem]:table-header-group'
                "
            >
                <tr role="row">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        role="columnheader"
                        scope="col"
                        class="p-3"
                        :class="{ 'text-right': column.key === 'actions' }"
                        :style="
                            column.width ? { width: column.width } : undefined
                        "
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody
                role="rowgroup"
                class="block divide-y"
                :class="
                    wide
                        ? '@min-[64rem]:table-row-group'
                        : '@min-[48rem]:table-row-group'
                "
            >
                <tr
                    v-for="(row, index) in rows"
                    :key="rowKey ? String(row[rowKey]) : index"
                    role="row"
                    class="grid grid-cols-2 gap-3 p-4 hover:bg-muted/30"
                    :class="
                        wide
                            ? '@min-[64rem]:table-row @min-[64rem]:p-0'
                            : '@min-[48rem]:table-row @min-[48rem]:p-0'
                    "
                >
                    <td
                        v-for="(column, columnIndex) in columns"
                        :key="column.key"
                        role="cell"
                        class="block min-w-0 align-middle break-words"
                        :class="[
                            wide
                                ? '@min-[64rem]:table-cell @min-[64rem]:p-3'
                                : '@min-[48rem]:table-cell @min-[48rem]:p-3',
                            {
                                'col-span-2':
                                    columnIndex === 0 ||
                                    column.key === 'actions',
                                'text-right': column.key === 'actions',
                            },
                        ]"
                    >
                        <span
                            aria-hidden="true"
                            class="mb-1 block text-xs text-muted-foreground"
                            :class="[
                                wide
                                    ? '@min-[64rem]:hidden'
                                    : '@min-[48rem]:hidden',
                                {
                                    'sr-only':
                                        columnIndex === 0 ||
                                        column.key === 'actions',
                                },
                            ]"
                            >{{ column.label }}</span
                        >
                        <slot
                            :name="'cell-' + column.key"
                            :row="row"
                            :index="index"
                            >{{ value(row, column.key) }}</slot
                        >
                        <RecordMetadata
                            v-if="
                                columnIndex === 0 &&
                                (creation(row).createdAt ||
                                    creation(row).createdBy)
                            "
                            v-bind="creation(row)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
