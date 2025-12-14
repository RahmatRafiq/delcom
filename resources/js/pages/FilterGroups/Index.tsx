import DataTableWrapper, { DataTableWrapperRef } from '@/components/datatables';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, FilterGroup } from '@/types';
import type { DataTableColumn } from '@/types/DataTables';
import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Filter Groups', href: route('filter-groups.index') }];

export default function FilterGroupsIndex({ success }: { success?: string }) {
    const dtRef = useRef<DataTableWrapperRef>(null);

    const columns: DataTableColumn<FilterGroup>[] = [
        { data: 'id', title: 'ID', className: 'all', width: '60px' },
        { data: 'name', title: 'Name', className: 'all' },
        {
            data: 'description',
            title: 'Description',
            className: 'tablet-p',
            render: (data: FilterGroup[keyof FilterGroup] | null) => {
                const value = typeof data === 'string' ? data : '';
                return value ? `<span class="text-muted-foreground text-sm">${value.substring(0, 50)}${value.length > 50 ? '...' : ''}</span>` : '-';
            },
        },
        {
            data: 'filters_count',
            title: 'Filters',
            className: 'tablet-p',
            width: '80px',
            render: (data: FilterGroup[keyof FilterGroup] | null) => {
                const count = typeof data === 'number' ? data : 0;
                return `<span class="inline-flex items-center justify-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">${count}</span>`;
            },
        },
        {
            data: 'applies_to_platforms',
            title: 'Platforms',
            className: 'tablet-l',
            render: (data: FilterGroup[keyof FilterGroup] | null) => {
                if (!data || !Array.isArray(data) || data.length === 0) {
                    return '<span class="text-muted-foreground text-xs">All platforms</span>';
                }
                return data
                    .map(
                        (p) =>
                            `<span class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium mr-1 mb-1">${p}</span>`,
                    )
                    .join('');
            },
        },
        {
            data: 'is_active',
            title: 'Status',
            className: 'all',
            width: '80px',
            render: (data: FilterGroup[keyof FilterGroup] | null) => {
                const isActive = data === true;
                return isActive
                    ? '<span class="inline-flex items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">Active</span>'
                    : '<span class="inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400">Inactive</span>';
            },
        },
        {
            data: null,
            title: 'Actions',
            orderable: false,
            searchable: false,
            className: 'all',
            width: '150px',
            render: (_data, _type, row: FilterGroup) => {
                const btn = 'inline-block px-3 py-2 text-sm font-medium rounded text-white transition-colors';
                return `
                    <div class="flex flex-wrap gap-2 py-1">
                        <a href="/dashboard/filters?group=${row.id}" class="${btn} bg-blue-500 hover:bg-blue-600">Filters</a>
                        <a href="/dashboard/filter-groups/${row.id}/edit" class="${btn} bg-yellow-500 hover:bg-yellow-600">Edit</a>
                        <button class="btn-delete ${btn} bg-red-600 hover:bg-red-700" data-id="${row.id}">Delete</button>
                    </div>
                `;
            },
        },
    ];

    const handleDelete = (id: number | string) => {
        router.delete(route('filter-groups.destroy', id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => dtRef.current?.reload(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Filter Groups" />
            <PageContainer maxWidth="full">
                <Heading title="Comment Moderation" />
                <HeadingSmall title="Filter Groups" description="Organize your filters into groups for better management" />
                <div className="mb-4 flex items-center justify-between gap-4">
                    <div className="flex gap-2">
                        <Link href={route('preset-filters.index')}>
                            <Button variant="outline">Browse Presets</Button>
                        </Link>
                    </div>
                    <Link href={route('filter-groups.create')}>
                        <Button>Create Group</Button>
                    </Link>
                </div>
                {success && <div className="mb-2 rounded bg-green-100 p-2 text-green-800 dark:bg-green-900/30 dark:text-green-400">{success}</div>}
                <DataTableWrapper<FilterGroup>
                    ref={dtRef}
                    ajax={{
                        url: route('filter-groups.json'),
                        type: 'POST',
                    }}
                    columns={columns}
                    onRowDelete={handleDelete}
                    confirmationConfig={{
                        delete: {
                            title: 'Delete Filter Group',
                            message: 'Are you sure you want to delete this filter group? All filters in this group will also be deleted.',
                            confirmText: 'Delete',
                            cancelText: 'Cancel',
                            successMessage: 'Filter group deleted successfully',
                        },
                    }}
                />
            </PageContainer>
        </AppLayout>
    );
}
