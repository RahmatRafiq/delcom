import DataTableWrapper, { DataTableWrapperRef } from '@/components/datatables';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import CustomSelect from '@/components/select';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ModerationLog, Platform } from '@/types';
import type { DataTableColumn } from '@/types/DataTables';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Download, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    platforms: Platform[];
}

interface Stats {
    total: number;
    today: number;
    this_week: number;
    by_action: Record<string, number>;
    by_source: Record<string, number>;
    by_platform: Record<string, number>;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Moderation Logs', href: route('moderation-logs.index') }];

const actionLabels: Record<string, { label: string; class: string }> = {
    deleted: { label: 'Deleted', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    hidden: { label: 'Hidden', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
    flagged: { label: 'Flagged', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    reported: { label: 'Reported', class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
    failed: { label: 'Failed', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' },
};

const sourceLabels: Record<string, string> = {
    background_job: 'Auto (API)',
    extension: 'Extension',
    manual: 'Manual',
};

export default function ModerationLogsIndex({ platforms }: Props) {
    const dtRef = useRef<DataTableWrapperRef>(null);
    const [stats, setStats] = useState<Stats | null>(null);
    const [filters, setFilters] = useState({
        platform_id: '',
        action_taken: '',
        date_from: '',
        date_to: '',
    });

    useEffect(() => {
        fetchStats();
    }, []);

    const fetchStats = async () => {
        try {
            const response = await axios.get(route('moderation-logs.stats'));
            setStats(response.data);
        } catch (error) {
            console.error('Failed to fetch stats:', error);
        }
    };

    const handleFilterChange = (key: keyof typeof filters, value: string) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        dtRef.current?.reload();
    };

    const resetFilters = () => {
        setFilters({
            platform_id: '',
            action_taken: '',
            date_from: '',
            date_to: '',
        });
        setTimeout(() => dtRef.current?.reload(), 0);
    };

    const handleExport = () => {
        window.location.href = route('moderation-logs.export');
    };

    const platformOptions = [
        { value: '', label: 'All Platforms' },
        ...platforms.map((p) => ({ value: String(p.id), label: p.display_name })),
    ];

    const actionOptions = [
        { value: '', label: 'All Actions' },
        { value: 'deleted', label: 'Deleted' },
        { value: 'hidden', label: 'Hidden' },
        { value: 'flagged', label: 'Flagged' },
        { value: 'reported', label: 'Reported' },
        { value: 'failed', label: 'Failed' },
    ];

    const columns: DataTableColumn<ModerationLog>[] = [
        {
            data: 'platform_icon',
            title: 'Platform',
            className: 'all',
            width: '100px',
            render: (_data, _type, row: ModerationLog & { platform_name?: string }) => {
                return `<span class="text-sm">${row.platform_name || 'Unknown'}</span>`;
            },
        },
        {
            data: 'commenter_username',
            title: 'Commenter',
            className: 'tablet-p',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const value = typeof data === 'string' ? data : 'Unknown';
                return `<span class="text-sm font-medium">@${value}</span>`;
            },
        },
        {
            data: 'comment_text',
            title: 'Comment',
            className: 'all',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const text = typeof data === 'string' ? data : '';
                const truncated = text.length > 60 ? text.substring(0, 60) + '...' : text;
                return `<span class="text-muted-foreground text-sm">${truncated}</span>`;
            },
        },
        {
            data: 'matched_pattern',
            title: 'Matched Pattern',
            className: 'tablet-l',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const pattern = typeof data === 'string' ? data : '';
                if (!pattern) return '-';
                const truncated = pattern.length > 25 ? pattern.substring(0, 25) + '...' : pattern;
                return `<code class="bg-muted rounded px-1.5 py-0.5 text-xs">${truncated}</code>`;
            },
        },
        {
            data: 'action_taken',
            title: 'Action',
            className: 'all',
            width: '90px',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const action = typeof data === 'string' ? data : '';
                const config = actionLabels[action] || { label: action, class: 'bg-gray-100 text-gray-700' };
                return `<span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium ${config.class}">${config.label}</span>`;
            },
        },
        {
            data: 'action_source',
            title: 'Source',
            className: 'tablet-p',
            width: '90px',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const source = typeof data === 'string' ? data : '';
                return `<span class="text-muted-foreground text-xs">${sourceLabels[source] || source}</span>`;
            },
        },
        {
            data: 'processed_at',
            title: 'Date',
            className: 'tablet-l',
            width: '150px',
            render: (data: ModerationLog[keyof ModerationLog] | null) => {
                const date = typeof data === 'string' ? data : '';
                return `<span class="text-muted-foreground text-xs">${date}</span>`;
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Moderation Logs" />
            <PageContainer maxWidth="full">
                <Heading title="Comment Moderation" />
                <HeadingSmall title="Moderation Logs" description="View all moderated comments across your connected platforms" />

                {/* Stats Cards */}
                {stats && (
                    <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardContent className="p-4">
                                <div className="text-muted-foreground text-sm">Total Moderated</div>
                                <div className="text-2xl font-bold">{stats.total.toLocaleString()}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="text-muted-foreground text-sm">Today</div>
                                <div className="text-2xl font-bold">{stats.today.toLocaleString()}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="text-muted-foreground text-sm">This Week</div>
                                <div className="text-2xl font-bold">{stats.this_week.toLocaleString()}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="text-muted-foreground text-sm">By Platform</div>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {Object.entries(stats.by_platform || {}).map(([platform, count]) => (
                                        <span key={platform} className="text-xs">
                                            {platform}: <strong>{count}</strong>
                                        </span>
                                    ))}
                                    {Object.keys(stats.by_platform || {}).length === 0 && (
                                        <span className="text-muted-foreground text-xs">No data yet</span>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Filters */}
                <div className="mb-4 rounded-lg border p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-2">
                            <Label>Platform</Label>
                            <CustomSelect
                                options={platformOptions}
                                value={platformOptions.find((o) => o.value === filters.platform_id)}
                                onChange={(selected) => handleFilterChange('platform_id', (selected as { value: string })?.value || '')}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Action</Label>
                            <CustomSelect
                                options={actionOptions}
                                value={actionOptions.find((o) => o.value === filters.action_taken)}
                                onChange={(selected) => handleFilterChange('action_taken', (selected as { value: string })?.value || '')}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>From Date</Label>
                            <Input
                                type="date"
                                value={filters.date_from}
                                onChange={(e) => handleFilterChange('date_from', e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>To Date</Label>
                            <Input
                                type="date"
                                value={filters.date_to}
                                onChange={(e) => handleFilterChange('date_to', e.target.value)}
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button onClick={applyFilters} className="flex-1">
                                Apply
                            </Button>
                            <Button variant="outline" onClick={resetFilters}>
                                <RefreshCw className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Export Button */}
                <div className="mb-4 flex justify-end">
                    <Button variant="outline" onClick={handleExport}>
                        <Download className="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                </div>

                <DataTableWrapper<ModerationLog>
                    ref={dtRef}
                    ajax={{
                        url: route('moderation-logs.json'),
                        type: 'POST',
                        data: (d: Record<string, unknown>) => {
                            return {
                                ...d,
                                ...filters,
                            };
                        },
                    }}
                    columns={columns}
                />
            </PageContainer>
        </AppLayout>
    );
}
