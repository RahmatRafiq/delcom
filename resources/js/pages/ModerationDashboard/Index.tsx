import { Head, router } from '@inertiajs/react';
import { Activity, AlertCircle, AlertTriangle, CheckCircle, Clock, ListChecks, RefreshCw, Search, Trash2, XCircle, Youtube } from 'lucide-react';
import { useState } from 'react';

import Heading from '@/components/heading';
import PageContainer from '@/components/page-container';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface QuotaStats {
    used: number;
    limit: number;
    remaining: number;
    percentage: number;
    reset_at: string;
    can_delete_comments: number;
}

interface Platform {
    id: number;
    platform_id: number;
    platform_name: string;
    platform_display_name: string;
    username: string;
    channel_id: string;
    auto_moderation_enabled: boolean;
    scan_frequency_minutes: number;
    last_scanned_at: string | null;
    last_scanned_ago: string | null;
}

interface TodayStats {
    total_scanned: number;
    total_deleted: number;
    total_failed: number;
}

interface RecentLog {
    id: number;
    platform: string;
    comment_text: string;
    commenter_username: string;
    matched_pattern: string;
    action_taken: string;
    processed_at: string;
    processed_ago: string;
}

interface Props {
    quotaStats: QuotaStats | null;
    platforms: Platform[];
    todayStats: TodayStats;
    recentLogs: RecentLog[];
    pendingCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Moderation', href: '/dashboard/moderation' },
];

export default function ModerationDashboard({ quotaStats, platforms, todayStats, recentLogs, pendingCount }: Props) {
    const [scanningPlatform, setScanningPlatform] = useState<number | null>(null);
    const [scanningAll, setScanningAll] = useState(false);

    const handleScan = (platformId: number) => {
        setScanningPlatform(platformId);
        router.post(
            route('moderation.scan', platformId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setScanningPlatform(null),
            },
        );
    };

    const handleScanAll = () => {
        setScanningAll(true);
        router.post(
            route('moderation.scan-all'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setScanningAll(false),
            },
        );
    };

    const getActionBadge = (action: string) => {
        switch (action) {
            case 'deleted':
                return <Badge variant="destructive">Deleted</Badge>;
            case 'hidden':
                return <Badge variant="secondary">Hidden</Badge>;
            case 'flagged':
                return <Badge variant="outline">Flagged</Badge>;
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            default:
                return <Badge>{action}</Badge>;
        }
    };

    const getPlatformIcon = (platformName: string) => {
        switch (platformName) {
            case 'youtube':
                return <Youtube className="h-5 w-5 text-red-500" />;
            default:
                return <Activity className="h-5 w-5" />;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Moderation Dashboard" />
            <PageContainer>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading title="Moderation Dashboard" description="Monitor and control comment moderation across your channels" />
                        <Button onClick={handleScanAll} disabled={scanningAll || platforms.length === 0}>
                            {scanningAll ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Scanning...
                                </>
                            ) : (
                                <>
                                    <Search className="mr-2 h-4 w-4" />
                                    Scan All Channels
                                </>
                            )}
                        </Button>
                    </div>

                    {/* Pending Review Alert */}
                    {pendingCount > 0 && (
                        <Alert className="border-yellow-500 bg-yellow-50 dark:bg-yellow-950/20">
                            <AlertTriangle className="h-4 w-4 text-yellow-600" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>
                                    <strong>{pendingCount}</strong> comment(s) pending review. Review before deleting to save API quota.
                                </span>
                                <Button size="sm" variant="outline" onClick={() => router.visit(route('review-queue.index'))}>
                                    <ListChecks className="mr-2 h-4 w-4" />
                                    Review Now
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Stats Row */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {/* Pending Review */}
                        <Card className={pendingCount > 0 ? 'border-yellow-500' : ''}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Pending Review</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center">
                                    <ListChecks className={`mr-2 h-8 w-8 ${pendingCount > 0 ? 'text-yellow-500' : 'text-muted-foreground'}`} />
                                    <div>
                                        <p className="text-2xl font-bold">{pendingCount}</p>
                                        <p className="text-muted-foreground text-xs">awaiting action</p>
                                    </div>
                                </div>
                                {pendingCount > 0 && (
                                    <Button size="sm" variant="outline" className="mt-2 w-full" onClick={() => router.visit(route('review-queue.index'))}>
                                        Review Queue
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        {/* Today Scanned */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Scanned Today</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center">
                                    <Search className="text-muted-foreground mr-2 h-8 w-8" />
                                    <div>
                                        <p className="text-2xl font-bold">{todayStats.total_scanned}</p>
                                        <p className="text-muted-foreground text-xs">comments processed</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Today Deleted */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Deleted Today</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center">
                                    <Trash2 className="mr-2 h-8 w-8 text-red-500" />
                                    <div>
                                        <p className="text-2xl font-bold">{todayStats.total_deleted}</p>
                                        <p className="text-muted-foreground text-xs">spam removed</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Failed Today */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Failed Today</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center">
                                    <XCircle className="text-muted-foreground mr-2 h-8 w-8" />
                                    <div>
                                        <p className="text-2xl font-bold">{todayStats.total_failed}</p>
                                        <p className="text-muted-foreground text-xs">actions failed</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {quotaStats && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">API Quota (Admin)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>
                                            {quotaStats.used.toLocaleString()} / {quotaStats.limit.toLocaleString()}
                                        </span>
                                        <span className="text-muted-foreground">{quotaStats.percentage}%</span>
                                    </div>
                                    <Progress value={quotaStats.percentage} className={quotaStats.percentage >= 80 ? 'bg-red-100' : ''} />
                                    <p className="text-muted-foreground text-xs">Can delete ~{quotaStats.can_delete_comments} more comments</p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {quotaStats && quotaStats.percentage >= 80 && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                API quota is at {quotaStats.percentage}%. Consider reducing scan frequency.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Connected Platforms */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Connected Channels</CardTitle>
                            <CardDescription>Click "Scan Now" to manually check for spam comments</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {platforms.length === 0 ? (
                                <div className="py-8 text-center">
                                    <Activity className="text-muted-foreground mx-auto h-12 w-12" />
                                    <p className="text-muted-foreground mt-2">No platforms connected</p>
                                    <Button variant="outline" className="mt-4" onClick={() => router.visit(route('connected-accounts.index'))}>
                                        Connect a Platform
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {platforms.map((platform) => (
                                        <div key={platform.id} className="flex items-center justify-between rounded-lg border p-4">
                                            <div className="flex items-center space-x-4">
                                                {getPlatformIcon(platform.platform_name)}
                                                <div>
                                                    <p className="font-medium">{platform.platform_display_name}</p>
                                                    <p className="text-muted-foreground text-sm">{platform.username || platform.channel_id}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-4">
                                                <div className="text-right text-sm">
                                                    <div className="text-muted-foreground flex items-center">
                                                        <Clock className="mr-1 h-3 w-3" />
                                                        {platform.last_scanned_ago || 'Never scanned'}
                                                    </div>
                                                    {platform.auto_moderation_enabled && (
                                                        <Badge variant="outline" className="mt-1">
                                                            Auto: every {platform.scan_frequency_minutes}m
                                                        </Badge>
                                                    )}
                                                </div>
                                                <Button size="sm" onClick={() => handleScan(platform.id)} disabled={scanningPlatform === platform.id}>
                                                    {scanningPlatform === platform.id ? (
                                                        <>
                                                            <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                                            Scanning...
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Search className="mr-2 h-4 w-4" />
                                                            Scan Now
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Activity */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Recent Activity</CardTitle>
                                    <CardDescription>Latest moderation actions taken</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" onClick={() => router.visit(route('moderation-logs.index'))}>
                                    View All Logs
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {recentLogs.length === 0 ? (
                                <div className="py-8 text-center">
                                    <CheckCircle className="text-muted-foreground mx-auto h-12 w-12" />
                                    <p className="text-muted-foreground mt-2">No moderation activity yet</p>
                                    <p className="text-muted-foreground text-sm">Run a scan to start moderating comments</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {recentLogs.map((log) => (
                                        <div key={log.id} className="flex items-start justify-between border-b pb-4 last:border-0">
                                            <div className="flex-1 space-y-1">
                                                <div className="flex items-center gap-2">
                                                    {getActionBadge(log.action_taken)}
                                                    <span className="text-muted-foreground text-sm">{log.platform}</span>
                                                    <span className="text-muted-foreground text-xs">• {log.processed_ago}</span>
                                                </div>
                                                <p className="text-sm">{log.comment_text}...</p>
                                                <p className="text-muted-foreground text-xs">
                                                    By @{log.commenter_username} • Matched: "{log.matched_pattern}"
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
