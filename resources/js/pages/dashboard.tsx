import Heading from '@/components/heading';
import PageContainer from '@/components/page-container';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { type AppSetting, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Activity, CheckCircle, Server, Users } from 'lucide-react';

interface QuotaStats {
    used: number;
    limit: number;
    remaining: number;
    percentage: number;
    reset_at: string;
    can_delete_comments: number;
}

interface AdminStats {
    quota: QuotaStats;
    totalUsers: number;
    totalConnectedPlatforms: number;
    todayModerations: number;
    todaySuccessful: number;
}

type PageProps = {
    appSettings: AppSetting;
    adminStats?: AdminStats;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { appSettings, adminStats } = usePage<PageProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head>
                <title>{appSettings.seo_title || appSettings.app_name || 'Dashboard'}</title>
                {appSettings.seo_description && <meta name="description" content={appSettings.seo_description} />}
                {appSettings.seo_keywords && <meta name="keywords" content={appSettings.seo_keywords} />}
                {appSettings.seo_og_image && <meta property="og:image" content={appSettings.seo_og_image} />}
                {appSettings.app_favicon && <link rel="icon" href={appSettings.app_favicon} />}
            </Head>
            <PageContainer maxWidth="full">
                <Heading title="Dashboard" description="Welcome back! Here's an overview of your application." />

                {adminStats && (
                    <div className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">YouTube API Quota</CardTitle>
                                    <Server className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {adminStats.quota.used.toLocaleString()} / {adminStats.quota.limit.toLocaleString()}
                                    </div>
                                    <Progress value={adminStats.quota.percentage} className={`mt-2 ${adminStats.quota.percentage >= 80 ? 'bg-red-100' : ''}`} />
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {adminStats.quota.remaining.toLocaleString()} remaining ({adminStats.quota.percentage}% used)
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                                    <Users className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.totalUsers}</div>
                                    <p className="text-muted-foreground text-xs">Registered users</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Connected Platforms</CardTitle>
                                    <Activity className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.totalConnectedPlatforms}</div>
                                    <p className="text-muted-foreground text-xs">Active connections</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Today's Moderations</CardTitle>
                                    <CheckCircle className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.todaySuccessful}</div>
                                    <p className="text-muted-foreground text-xs">
                                        {adminStats.todayModerations} total processed
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                                <CardDescription>Common administrative tasks</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-2 md:grid-cols-4">
                                    <a href="/dashboard/users" className="hover:bg-muted rounded-lg border p-4 transition-colors">
                                        <Users className="mb-2 h-6 w-6" />
                                        <p className="font-medium">Manage Users</p>
                                    </a>
                                    <a href="/dashboard/moderation" className="hover:bg-muted rounded-lg border p-4 transition-colors">
                                        <Activity className="mb-2 h-6 w-6" />
                                        <p className="font-medium">Moderation Dashboard</p>
                                    </a>
                                    <a href="/dashboard/moderation-logs" className="hover:bg-muted rounded-lg border p-4 transition-colors">
                                        <CheckCircle className="mb-2 h-6 w-6" />
                                        <p className="font-medium">View Logs</p>
                                    </a>
                                    <a href="/dashboard/app-settings" className="hover:bg-muted rounded-lg border p-4 transition-colors">
                                        <Server className="mb-2 h-6 w-6" />
                                        <p className="font-medium">App Settings</p>
                                    </a>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {!adminStats && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Welcome to DelCom</CardTitle>
                            <CardDescription>Your comment moderation assistant</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground">
                                Get started by connecting your YouTube channel and setting up your filters.
                            </p>
                            <div className="mt-4 flex gap-2">
                                <a href="/dashboard/connected-accounts" className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium">
                                    Connect Channel
                                </a>
                                <a href="/dashboard/filters" className="border-input bg-background hover:bg-accent hover:text-accent-foreground inline-flex items-center rounded-md border px-4 py-2 text-sm font-medium">
                                    Setup Filters
                                </a>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </PageContainer>
        </AppLayout>
    );
}
