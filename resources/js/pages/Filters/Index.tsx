import DataTableWrapper, { DataTableWrapperRef } from '@/components/datatables';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import CustomSelect from '@/components/select';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Filter, FilterGroup } from '@/types';
import type { DataTableColumn } from '@/types/DataTables';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    filterGroups: FilterGroup[];
    selectedGroupId?: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Filter Groups', href: route('filter-groups.index') },
    { title: 'Filters', href: route('filters.index') },
];

const typeLabels: Record<string, string> = {
    keyword: 'Keyword',
    phrase: 'Phrase',
    regex: 'Regex',
    username: 'Username',
    url: 'URL',
    emoji_spam: 'Emoji Spam',
    repeat_char: 'Repeat Char',
};

const actionLabels: Record<string, { label: string; class: string }> = {
    delete: { label: 'Delete', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    hide: { label: 'Hide', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
    flag: { label: 'Flag', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    report: { label: 'Report', class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
};

export default function FiltersIndex({ filterGroups, selectedGroupId }: Props) {
    const dtRef = useRef<DataTableWrapperRef>(null);
    const [currentGroupId, setCurrentGroupId] = useState<string | null>(selectedGroupId ?? null);

    const groupOptions = [{ value: '', label: 'All Groups' }, ...filterGroups.map((g) => ({ value: String(g.id), label: g.name }))];

    useEffect(() => {
        if (dtRef.current) {
            dtRef.current.reload();
        }
    }, [currentGroupId]);

    const columns: DataTableColumn<Filter>[] = [
        { data: 'id', title: 'ID', className: 'all', width: '60px' },
        {
            data: 'filter_group_name',
            title: 'Group',
            className: 'tablet-p',
            render: (data: Filter[keyof Filter] | null) => {
                const value = typeof data === 'string' ? data : 'Unknown';
                return `<span class="text-muted-foreground text-sm">${value}</span>`;
            },
        },
        {
            data: 'type',
            title: 'Type',
            className: 'all',
            width: '100px',
            render: (data: Filter[keyof Filter] | null) => {
                const type = typeof data === 'string' ? data : '';
                return `<span class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium">${typeLabels[type] || type}</span>`;
            },
        },
        {
            data: 'pattern',
            title: 'Pattern',
            className: 'all',
            render: (data: Filter[keyof Filter] | null) => {
                const pattern = typeof data === 'string' ? data : '';
                const truncated = pattern.length > 40 ? pattern.substring(0, 40) + '...' : pattern;
                return `<code class="bg-muted rounded px-1.5 py-0.5 text-sm">${truncated}</code>`;
            },
        },
        {
            data: 'action',
            title: 'Action',
            className: 'tablet-p',
            width: '80px',
            render: (data: Filter[keyof Filter] | null) => {
                const action = typeof data === 'string' ? data : '';
                const config = actionLabels[action] || { label: action, class: 'bg-gray-100 text-gray-700' };
                return `<span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium ${config.class}">${config.label}</span>`;
            },
        },
        {
            data: 'hit_count',
            title: 'Hits',
            className: 'tablet-l',
            width: '70px',
            render: (data: Filter[keyof Filter] | null) => {
                const count = typeof data === 'number' ? data : 0;
                return `<span class="text-muted-foreground">${count.toLocaleString()}</span>`;
            },
        },
        {
            data: 'is_active',
            title: 'Status',
            className: 'all',
            width: '80px',
            render: (data: Filter[keyof Filter] | null) => {
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
            width: '130px',
            render: (_data, _type, row: Filter) => {
                const btn = 'inline-block px-3 py-2 text-sm font-medium rounded text-white transition-colors';
                return `
                    <div class="flex flex-wrap gap-2 py-1">
                        <a href="/dashboard/filters/${row.id}/edit" class="${btn} bg-yellow-500 hover:bg-yellow-600">Edit</a>
                        <button class="btn-delete ${btn} bg-red-600 hover:bg-red-700" data-id="${row.id}">Delete</button>
                    </div>
                `;
            },
        },
    ];

    const handleDelete = (id: number | string) => {
        router.delete(route('filters.destroy', id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => dtRef.current?.reload(),
        });
    };

    const handleGroupChange = (selected: { value: string } | null) => {
        const newGroupId = selected?.value || null;
        setCurrentGroupId(newGroupId);
        // Update URL without full page reload
        const url = newGroupId ? `${route('filters.index')}?group=${newGroupId}` : route('filters.index');
        window.history.replaceState({}, '', url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Filters" />
            <PageContainer maxWidth="full">
                <Heading title="Comment Moderation" />
                <HeadingSmall title="Filters" description="Define patterns to automatically moderate comments" />
                <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="w-full sm:w-64">
                        <CustomSelect
                            options={groupOptions}
                            value={groupOptions.find((o) => o.value === (currentGroupId || ''))}
                            onChange={(selected) => handleGroupChange(selected as { value: string } | null)}
                            placeholder="Filter by group"
                        />
                    </div>
                    <Link href={route('filters.create', currentGroupId ? { group: currentGroupId } : {})}>
                        <Button>Create Filter</Button>
                    </Link>
                </div>
                <DataTableWrapper<Filter>
                    ref={dtRef}
                    ajax={{
                        url: route('filters.json'),
                        type: 'POST',
                        data: (d: Record<string, unknown>) => {
                            return {
                                ...d,
                                group_id: currentGroupId,
                            };
                        },
                    }}
                    columns={columns}
                    onRowDelete={handleDelete}
                    confirmationConfig={{
                        delete: {
                            title: 'Delete Filter',
                            message: 'Are you sure you want to delete this filter?',
                            confirmText: 'Delete',
                            cancelText: 'Cancel',
                            successMessage: 'Filter deleted successfully',
                        },
                    }}
                />
            </PageContainer>
        </AppLayout>
    );
}
