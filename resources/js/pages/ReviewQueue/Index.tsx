import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Check, CheckCircle, Clock, ExternalLink, RefreshCw, Trash2, X, XCircle, Youtube } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import Heading from '@/components/heading';
import PageContainer from '@/components/page-container';
import CustomSelect from '@/components/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Platform {
    id: number;
    name: string;
}

interface StatusCounts {
    pending?: number;
    approved?: number;
    dismissed?: number;
    deleted?: number;
    failed?: number;
}

interface Props {
    statusCounts: StatusCounts;
    platforms: Platform[];
}

interface PendingItem {
    id: number;
    platform_name: string;
    platform_icon: string;
    video_id: string;
    video_title: string;
    commenter_username: string;
    commenter_profile_url: string | null;
    comment_text: string;
    matched_pattern: string;
    matched_filter_type: string | null;
    confidence_score: number;
    status: string;
    detected_at: string;
    detected_ago: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Moderation', href: '/dashboard/moderation' },
    { title: 'Review Queue', href: '/dashboard/review-queue' },
];

const statusConfig: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
    pending: { label: 'Pending', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', icon: <Clock className="h-3 w-3" /> },
    approved: { label: 'Approved', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', icon: <Check className="h-3 w-3" /> },
    dismissed: { label: 'Dismissed', color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400', icon: <X className="h-3 w-3" /> },
    deleted: { label: 'Deleted', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: <Trash2 className="h-3 w-3" /> },
    failed: { label: 'Failed', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', icon: <XCircle className="h-3 w-3" /> },
};

export default function ReviewQueueIndex({ statusCounts, platforms }: Props) {
    const [items, setItems] = useState<PendingItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [processing, setProcessing] = useState(false);
    const [filters, setFilters] = useState({
        status: 'pending',
        platform_id: '',
        search: '',
    });
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    const pendingCount = statusCounts.pending || 0;
    const approvedCount = statusCounts.approved || 0;

    useEffect(() => {
        fetchItems();
    }, [filters.status, filters.platform_id]);

    useEffect(() => {
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }
        searchTimeoutRef.current = setTimeout(() => {
            fetchItems();
        }, 300);

        return () => {
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }
        };
    }, [filters.search]);

    const fetchItems = async () => {
        setLoading(true);
        try {
            const response = await axios.post(route('review-queue.json'), {
                status: filters.status,
                platform_id: filters.platform_id,
                search: { value: filters.search },
                start: 0,
                length: 100,
            });
            setItems(response.data.data);
            setSelectedIds(new Set());
        } catch (error) {
            console.error('Failed to fetch items:', error);
        } finally {
            setLoading(false);
        }
    };

    const toggleSelect = (id: number) => {
        const newSelected = new Set(selectedIds);
        if (newSelected.has(id)) {
            newSelected.delete(id);
        } else {
            newSelected.add(id);
        }
        setSelectedIds(newSelected);
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === items.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(items.map((i) => i.id)));
        }
    };

    const handleApprove = () => {
        if (selectedIds.size === 0) return;
        setProcessing(true);
        router.post(
            route('review-queue.approve'),
            { ids: Array.from(selectedIds) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    fetchItems();
                },
            },
        );
    };

    const handleDismiss = () => {
        if (selectedIds.size === 0) return;
        setProcessing(true);
        router.post(
            route('review-queue.dismiss'),
            { ids: Array.from(selectedIds) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    fetchItems();
                },
            },
        );
    };

    const handleDelete = () => {
        if (selectedIds.size === 0) return;
        if (!confirm(`Are you sure you want to delete ${selectedIds.size} comment(s)? This uses API quota (50 units each).`)) {
            return;
        }
        setProcessing(true);
        router.post(
            route('review-queue.delete'),
            { ids: Array.from(selectedIds) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    fetchItems();
                },
            },
        );
    };

    const handleApproveAll = () => {
        if (!confirm('Approve all pending items for deletion?')) return;
        setProcessing(true);
        router.post(
            route('review-queue.approve-all'),
            { platform_id: filters.platform_id },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    fetchItems();
                },
            },
        );
    };

    const handleDeleteApproved = () => {
        if (approvedCount === 0) return;
        if (!confirm(`Delete all ${approvedCount} approved comments? This uses API quota.`)) return;

        // Get approved items and delete them
        setProcessing(true);
        axios
            .post(route('review-queue.json'), {
                status: 'approved',
                start: 0,
                length: 1000,
            })
            .then((response) => {
                const ids = response.data.data.map((item: PendingItem) => item.id);
                if (ids.length > 0) {
                    router.post(
                        route('review-queue.delete'),
                        { ids },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                fetchItems();
                            },
                        },
                    );
                } else {
                    setProcessing(false);
                }
            })
            .catch(() => {
                setProcessing(false);
            });
    };

    const platformOptions = [{ value: '', label: 'All Platforms' }, ...platforms.map((p) => ({ value: String(p.id), label: p.name }))];

    const statusOptions = [
        { value: 'pending', label: `Pending (${pendingCount})` },
        { value: 'approved', label: `Approved (${approvedCount})` },
        { value: 'dismissed', label: 'Dismissed' },
        { value: 'deleted', label: 'Deleted' },
        { value: 'all', label: 'All' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review Queue" />
            <PageContainer maxWidth="full">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading title="Review Queue" description="Review detected spam before deletion to save API quota" />
                        <Button variant="outline" onClick={fetchItems} disabled={loading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card className={pendingCount > 0 ? 'border-yellow-500' : ''}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="text-muted-foreground text-sm">Pending Review</div>
                                        <div className="text-2xl font-bold">{pendingCount}</div>
                                    </div>
                                    <Clock className="h-8 w-8 text-yellow-500" />
                                </div>
                            </CardContent>
                        </Card>
                        <Card className={approvedCount > 0 ? 'border-blue-500' : ''}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="text-muted-foreground text-sm">Approved (Ready)</div>
                                        <div className="text-2xl font-bold">{approvedCount}</div>
                                    </div>
                                    <CheckCircle className="h-8 w-8 text-blue-500" />
                                </div>
                                {approvedCount > 0 && (
                                    <Button size="sm" variant="destructive" className="mt-2 w-full" onClick={handleDeleteApproved} disabled={processing}>
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete All Approved
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="text-muted-foreground text-sm">Dismissed</div>
                                        <div className="text-2xl font-bold">{statusCounts.dismissed || 0}</div>
                                    </div>
                                    <X className="text-muted-foreground h-8 w-8" />
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="text-muted-foreground text-sm">Deleted</div>
                                        <div className="text-2xl font-bold">{statusCounts.deleted || 0}</div>
                                    </div>
                                    <Trash2 className="h-8 w-8 text-red-500" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {pendingCount > 0 && (
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                You have {pendingCount} comment(s) pending review. Review and approve them, then delete to save API quota.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Filters */}
                    <div className="flex flex-wrap items-end gap-4 rounded-lg border p-4">
                        <div className="min-w-[150px] space-y-2">
                            <Label>Status</Label>
                            <CustomSelect
                                options={statusOptions}
                                value={statusOptions.find((o) => o.value === filters.status)}
                                onChange={(selected) => setFilters((prev) => ({ ...prev, status: (selected as { value: string })?.value || 'pending' }))}
                            />
                        </div>
                        <div className="min-w-[150px] space-y-2">
                            <Label>Platform</Label>
                            <CustomSelect
                                options={platformOptions}
                                value={platformOptions.find((o) => o.value === filters.platform_id)}
                                onChange={(selected) => setFilters((prev) => ({ ...prev, platform_id: (selected as { value: string })?.value || '' }))}
                            />
                        </div>
                        <div className="min-w-[200px] flex-1 space-y-2">
                            <Label>Search</Label>
                            <Input
                                type="text"
                                placeholder="Search comments, usernames..."
                                value={filters.search}
                                onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
                            />
                        </div>
                    </div>

                    {/* Bulk Actions */}
                    {filters.status === 'pending' && items.length > 0 && (
                        <div className="flex flex-wrap items-center gap-4 rounded-lg border bg-muted/50 p-4">
                            <div className="flex items-center gap-2">
                                <Checkbox checked={selectedIds.size === items.length && items.length > 0} onCheckedChange={toggleSelectAll} />
                                <span className="text-sm">
                                    {selectedIds.size > 0 ? `${selectedIds.size} selected` : 'Select all'}
                                </span>
                            </div>
                            <div className="flex flex-1 flex-wrap gap-2">
                                <Button size="sm" variant="outline" onClick={handleApprove} disabled={selectedIds.size === 0 || processing}>
                                    <Check className="mr-2 h-4 w-4" />
                                    Approve Selected
                                </Button>
                                <Button size="sm" variant="outline" onClick={handleDismiss} disabled={selectedIds.size === 0 || processing}>
                                    <X className="mr-2 h-4 w-4" />
                                    Dismiss Selected
                                </Button>
                                <Button size="sm" variant="destructive" onClick={handleDelete} disabled={selectedIds.size === 0 || processing}>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Now ({selectedIds.size})
                                </Button>
                            </div>
                            <Button size="sm" variant="secondary" onClick={handleApproveAll} disabled={processing}>
                                Approve All Pending
                            </Button>
                        </div>
                    )}

                    {/* Items List */}
                    <div className="space-y-3">
                        {loading ? (
                            <div className="py-12 text-center">
                                <RefreshCw className="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                <p className="text-muted-foreground mt-2">Loading...</p>
                            </div>
                        ) : items.length === 0 ? (
                            <div className="py-12 text-center">
                                <CheckCircle className="mx-auto h-12 w-12 text-green-500" />
                                <p className="text-muted-foreground mt-2">No items found</p>
                                {filters.status === 'pending' && (
                                    <p className="text-muted-foreground text-sm">All caught up! Run a scan to detect more spam.</p>
                                )}
                            </div>
                        ) : (
                            items.map((item) => (
                                <Card key={item.id} className={selectedIds.has(item.id) ? 'ring-2 ring-primary' : ''}>
                                    <CardContent className="p-4">
                                        <div className="flex gap-4">
                                            {filters.status === 'pending' && (
                                                <div className="flex items-start pt-1">
                                                    <Checkbox checked={selectedIds.has(item.id)} onCheckedChange={() => toggleSelect(item.id)} />
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1 space-y-2">
                                                {/* Header */}
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Youtube className="h-4 w-4 text-red-500" />
                                                    <span className="font-medium">@{item.commenter_username}</span>
                                                    <Badge variant="outline" className={statusConfig[item.status]?.color}>
                                                        {statusConfig[item.status]?.icon}
                                                        <span className="ml-1">{statusConfig[item.status]?.label}</span>
                                                    </Badge>
                                                    <span className="text-muted-foreground text-xs">{item.detected_ago}</span>
                                                </div>

                                                {/* Comment Text */}
                                                <p className="text-sm">{item.comment_text}</p>

                                                {/* Meta */}
                                                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                    <span>
                                                        Matched: <code className="bg-muted rounded px-1 py-0.5">{item.matched_pattern}</code>
                                                    </span>
                                                    {item.video_title && (
                                                        <a
                                                            href={`https://www.youtube.com/watch?v=${item.video_id}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="flex items-center gap-1 hover:text-primary"
                                                        >
                                                            <ExternalLink className="h-3 w-3" />
                                                            {item.video_title.length > 40 ? item.video_title.substring(0, 40) + '...' : item.video_title}
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
