import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import CustomSelect from '@/components/select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, ConnectionMethod, UsageStats } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Calendar, CheckCircle2, Clock, Crown, Link2, Lock, Puzzle, Settings, Unlink, XCircle, Zap } from 'lucide-react';
import { useState } from 'react';

// Custom brand icons (Lucide deprecated brand icons)
const YoutubeIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
    </svg>
);

const InstagramIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
    </svg>
);

const TwitterIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
    </svg>
);

const TikTokIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z" />
    </svg>
);

const ThreadsIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.96-.065-1.182.408-2.256 1.332-3.023.85-.706 2.017-1.115 3.381-1.192 1.246-.07 2.39.048 3.455.347l.01.003c.078-1.867-.474-3.247-1.613-4.073-1.073-.778-2.539-1.095-4.012-.87l-.259-2.012c1.924-.293 3.876.116 5.381 1.207 1.632 1.182 2.527 3.108 2.469 5.662.36.194.695.411 1.003.652 1.117.872 1.873 2.055 2.18 3.419.367 1.63.037 3.573-1.64 5.215-1.87 1.832-4.175 2.632-7.461 2.657zm-.07-8.139c-.921.051-1.682.286-2.2.683-.455.35-.674.788-.651 1.305.03.62.396 1.16 1.03 1.522.576.329 1.315.466 2.082.427 1.157-.063 2.023-.469 2.58-1.208.503-.668.76-1.639.766-2.89-1.14-.253-2.318-.341-3.607-.24v.001z" />
    </svg>
);

interface PlatformConnectionMethod {
    method: ConnectionMethod;
    requires_business_account: boolean;
    requires_paid_api: boolean;
    notes: string | null;
    is_active: boolean;
    can_access: boolean;
}

interface PlatformConnection {
    id: number;
    platform_username: string;
    is_active: boolean;
    auto_moderation_enabled: boolean;
    auto_delete_enabled: boolean;
    scan_mode: 'full' | 'incremental' | 'manual';
    scan_frequency_minutes: number;
    last_scanned_at: string | null;
    token_expires_at: string | null;
    is_token_expired: boolean;
}

interface Platform {
    id: number;
    name: string;
    display_name: string;
    tier: 'api' | 'extension';
    connection_methods: PlatformConnectionMethod[];
    connections: Record<ConnectionMethod, PlatformConnection>;
    is_connected: boolean;
}

interface SubscriptionInfo {
    plan: {
        id: number;
        slug: string;
        name: string;
        monthly_action_limit: number;
        max_platforms: number;
    } | null;
    usage: UsageStats;
}

interface Props {
    platforms: Platform[];
    subscription: SubscriptionInfo;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Connected Accounts', href: route('connected-accounts.index') },
];

const platformIcons: Record<string, React.ReactNode> = {
    youtube: <YoutubeIcon className="h-6 w-6" />,
    instagram: <InstagramIcon className="h-6 w-6" />,
    twitter: <TwitterIcon className="h-6 w-6" />,
    threads: <ThreadsIcon className="h-6 w-6" />,
    tiktok: <TikTokIcon className="h-6 w-6" />,
};

const platformColors: Record<string, string> = {
    youtube: 'text-red-600 bg-red-50 dark:bg-red-900/20',
    instagram: 'text-pink-600 bg-pink-50 dark:bg-pink-900/20',
    twitter: 'text-sky-500 bg-sky-50 dark:bg-sky-900/20',
    threads: 'text-gray-800 bg-gray-100 dark:bg-gray-800 dark:text-gray-200',
    tiktok: 'text-black bg-gray-100 dark:bg-gray-800 dark:text-white',
};

const scanFrequencyOptions = [
    { value: 5, label: 'Every 5 minutes' },
    { value: 15, label: 'Every 15 minutes' },
    { value: 30, label: 'Every 30 minutes' },
    { value: 60, label: 'Every hour' },
    { value: 360, label: 'Every 6 hours' },
    { value: 720, label: 'Every 12 hours' },
    { value: 1440, label: 'Once a day' },
];

const scanModeOptions = [
    { value: 'incremental', label: 'Incremental (Recommended)', description: 'Only scan new comments since last scan' },
    { value: 'full', label: 'Full Scan', description: 'Scan all comments from the beginning' },
    { value: 'manual', label: 'Manual Only', description: 'Only scan when you click "Scan Now"' },
];

export default function ConnectedAccountsIndex({ platforms, subscription }: Props) {
    const [settingsDialogOpen, setSettingsDialogOpen] = useState(false);
    const [selectedPlatform, setSelectedPlatform] = useState<Platform | null>(null);
    const [selectedMethod, setSelectedMethod] = useState<ConnectionMethod>('api');
    const [settings, setSettings] = useState({
        is_active: true,
        auto_moderation_enabled: false,
        auto_delete_enabled: false,
        scan_mode: 'incremental' as 'full' | 'incremental' | 'manual',
        scan_frequency_minutes: 60,
    });
    const [saving, setSaving] = useState(false);

    const handleConnect = (platform: Platform, method: ConnectionMethod) => {
        router.post(route('connected-accounts.connect', platform.id), {
            connection_method: method,
        });
    };

    const handleDisconnect = (platform: Platform, method: ConnectionMethod) => {
        const connection = platform.connections[method];
        if (!connection) return;

        if (confirm(`Are you sure you want to disconnect ${platform.display_name} (${method})?`)) {
            router.delete(route('connected-accounts.destroy', connection.id));
        }
    };

    const handleOpenSettings = (platform: Platform, method: ConnectionMethod) => {
        const connection = platform.connections[method];
        if (!connection) return;

        setSelectedPlatform(platform);
        setSelectedMethod(method);
        setSettings({
            is_active: connection.is_active,
            auto_moderation_enabled: connection.auto_moderation_enabled,
            auto_delete_enabled: connection.auto_delete_enabled,
            scan_mode: connection.scan_mode,
            scan_frequency_minutes: connection.scan_frequency_minutes,
        });
        setSettingsDialogOpen(true);
    };

    const handleSaveSettings = () => {
        if (!selectedPlatform) return;
        const connection = selectedPlatform.connections[selectedMethod];
        if (!connection) return;

        setSaving(true);
        router.put(route('connected-accounts.update', connection.id), settings, {
            onSuccess: () => {
                setSettingsDialogOpen(false);
            },
            onFinish: () => {
                setSaving(false);
            },
        });
    };

    // Calculate usage display
    const usagePercentage = subscription.usage.percentage;
    const isUnlimited = subscription.usage.limit === 'unlimited';
    const isDailyUnlimited = subscription.usage.daily_limit === 'unlimited';
    const dailyRemaining = subscription.usage.daily_remaining;
    const monthlyRemaining = subscription.usage.remaining;
    const isDailyLimitReached = typeof dailyRemaining === 'number' && dailyRemaining <= 0;
    const isMonthlyLimitReached = typeof monthlyRemaining === 'number' && monthlyRemaining <= 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Connected Accounts" />
            <PageContainer maxWidth="4xl">
                <Heading title="Settings" />
                <HeadingSmall title="Connected Accounts" description="Connect your social media accounts to enable comment moderation." />

                {/* Subscription & Usage Card */}
                <Card className="mb-8">
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <span className="bg-primary/10 rounded-lg p-2.5">
                                    <Crown className="text-primary h-6 w-6" />
                                </span>
                                <div>
                                    <CardTitle className="text-base">{subscription.plan?.name || 'Free'} Plan</CardTitle>
                                    <CardDescription>Your moderation limits</CardDescription>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('subscription.plans')}>
                                    <Zap className="mr-2 h-4 w-4" />
                                    Upgrade
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {/* Daily Limit */}
                            <div className="space-y-2 rounded-lg border p-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                    <span className="text-sm font-medium">Daily Limit</span>
                                    {isDailyLimitReached && (
                                        <Badge variant="destructive" className="ml-auto">
                                            Reached
                                        </Badge>
                                    )}
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span>
                                        {subscription.usage.daily_used} / {isDailyUnlimited ? '∞' : subscription.usage.daily_limit}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {isDailyUnlimited ? 'Unlimited' : `${subscription.usage.daily_percentage}%`}
                                    </span>
                                </div>
                                {!isDailyUnlimited && (
                                    <Progress
                                        value={subscription.usage.daily_percentage}
                                        className={`h-2 ${subscription.usage.daily_percentage >= 80 ? 'bg-red-100' : ''}`}
                                    />
                                )}
                                <p className="text-muted-foreground text-xs">
                                    {isDailyUnlimited
                                        ? 'Unlimited deletions today'
                                        : `${subscription.usage.daily_remaining} deletions remaining today`}
                                </p>
                            </div>

                            {/* Monthly Limit */}
                            <div className="space-y-2 rounded-lg border p-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="text-muted-foreground h-4 w-4" />
                                    <span className="text-sm font-medium">Monthly Limit</span>
                                    {isMonthlyLimitReached && (
                                        <Badge variant="destructive" className="ml-auto">
                                            Reached
                                        </Badge>
                                    )}
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span>
                                        {subscription.usage.used} / {isUnlimited ? '∞' : subscription.usage.limit}
                                    </span>
                                    <span className="text-muted-foreground">{isUnlimited ? 'Unlimited' : `${subscription.usage.percentage}%`}</span>
                                </div>
                                {!isUnlimited && (
                                    <Progress
                                        value={subscription.usage.percentage}
                                        className={`h-2 ${subscription.usage.percentage >= 80 ? 'bg-red-100' : ''}`}
                                    />
                                )}
                                <p className="text-muted-foreground text-xs">
                                    {isUnlimited
                                        ? 'Unlimited deletions this month'
                                        : `${subscription.usage.remaining} remaining • Resets ${subscription.usage.reset_date}`}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Platforms Grid */}
                <div className="grid gap-6">
                    {platforms.map((platform) => (
                        <PlatformCard
                            key={platform.id}
                            platform={platform}
                            onConnect={handleConnect}
                            onDisconnect={handleDisconnect}
                            onSettings={handleOpenSettings}
                        />
                    ))}
                </div>

                {/* Settings Dialog */}
                <Dialog open={settingsDialogOpen} onOpenChange={setSettingsDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <span className={`rounded-lg p-2 ${platformColors[selectedPlatform?.name || '']}`}>
                                    {platformIcons[selectedPlatform?.name || '']}
                                </span>
                                {selectedPlatform?.display_name} Settings
                                <Badge variant="outline" className="ml-2">
                                    {selectedMethod === 'api' ? 'API' : 'Extension'}
                                </Badge>
                            </DialogTitle>
                            <DialogDescription>
                                Configure moderation settings for {selectedPlatform?.connections[selectedMethod]?.platform_username}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-6 py-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Connection Active</Label>
                                    <p className="text-muted-foreground text-sm">Enable or disable this connection</p>
                                </div>
                                <Switch
                                    checked={settings.is_active}
                                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, is_active: checked }))}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Auto Moderation</Label>
                                    <p className="text-muted-foreground text-sm">Automatically scan for spam comments on schedule</p>
                                </div>
                                <Switch
                                    checked={settings.auto_moderation_enabled}
                                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, auto_moderation_enabled: checked }))}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Auto Delete</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {settings.auto_delete_enabled
                                            ? 'Spam will be deleted immediately (50 quota/delete)'
                                            : 'Spam goes to Review Queue first (saves quota)'}
                                    </p>
                                </div>
                                <Switch
                                    checked={settings.auto_delete_enabled}
                                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, auto_delete_enabled: checked }))}
                                />
                            </div>

                            {selectedMethod === 'api' && (
                                <div className="space-y-2">
                                    <Label>Scan Mode</Label>
                                    <CustomSelect
                                        options={scanModeOptions}
                                        value={scanModeOptions.find((o) => o.value === settings.scan_mode)}
                                        onChange={(selected) =>
                                            setSettings((prev) => ({
                                                ...prev,
                                                scan_mode: (selected as { value: string })?.value as 'full' | 'incremental' | 'manual' ?? 'incremental',
                                            }))
                                        }
                                        placeholder="Select scan mode"
                                        formatOptionLabel={(option: { value: string; label: string; description?: string }) => (
                                            <div>
                                                <div className="font-medium">{option.label}</div>
                                                {option.description && <div className="text-muted-foreground text-xs">{option.description}</div>}
                                            </div>
                                        )}
                                    />
                                </div>
                            )}

                            {settings.auto_moderation_enabled && settings.scan_mode !== 'manual' && selectedMethod === 'api' && (
                                <div className="space-y-2">
                                    <Label>Scan Frequency</Label>
                                    <CustomSelect
                                        options={scanFrequencyOptions}
                                        value={scanFrequencyOptions.find((o) => o.value === settings.scan_frequency_minutes)}
                                        onChange={(selected) =>
                                            setSettings((prev) => ({
                                                ...prev,
                                                scan_frequency_minutes: (selected as { value: number })?.value ?? 60,
                                            }))
                                        }
                                        placeholder="Select frequency"
                                    />
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setSettingsDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleSaveSettings} disabled={saving}>
                                {saving ? 'Saving...' : 'Save Settings'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageContainer>
        </AppLayout>
    );
}

function PlatformCard({
    platform,
    onConnect,
    onDisconnect,
    onSettings,
}: {
    platform: Platform;
    onConnect: (platform: Platform, method: ConnectionMethod) => void;
    onDisconnect: (platform: Platform, method: ConnectionMethod) => void;
    onSettings: (platform: Platform, method: ConnectionMethod) => void;
}) {
    const hasAnyConnection = Object.keys(platform.connections).length > 0;

    return (
        <Card className={hasAnyConnection ? 'border-green-200 dark:border-green-800' : ''}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <span className={`rounded-lg p-2.5 ${platformColors[platform.name]}`}>{platformIcons[platform.name]}</span>
                        <div>
                            <CardTitle className="text-base">{platform.display_name}</CardTitle>
                            <CardDescription>
                                {platform.connection_methods.length > 1
                                    ? 'API & Extension available'
                                    : platform.connection_methods[0]?.method === 'api'
                                      ? 'API Integration'
                                      : 'Browser Extension'}
                            </CardDescription>
                        </div>
                    </div>
                    {hasAnyConnection ? (
                        <Badge variant="default" className="bg-green-600">
                            <CheckCircle2 className="mr-1 h-3 w-3" />
                            Connected
                        </Badge>
                    ) : (
                        <Badge variant="secondary">
                            <XCircle className="mr-1 h-3 w-3" />
                            Not Connected
                        </Badge>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {platform.connection_methods.map((cm) => (
                    <ConnectionMethodRow
                        key={cm.method}
                        connectionMethod={cm}
                        connection={platform.connections[cm.method]}
                        onConnect={() => onConnect(platform, cm.method)}
                        onDisconnect={() => onDisconnect(platform, cm.method)}
                        onSettings={() => onSettings(platform, cm.method)}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function ConnectionMethodRow({
    connectionMethod,
    connection,
    onConnect,
    onDisconnect,
    onSettings,
}: {
    connectionMethod: PlatformConnectionMethod;
    connection?: PlatformConnection;
    onConnect: () => void;
    onDisconnect: () => void;
    onSettings: () => void;
}) {
    const isConnected = !!connection;
    const canAccess = connectionMethod.can_access;
    const isApi = connectionMethod.method === 'api';

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <span
                        className={`rounded p-1.5 ${isApi ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30' : 'bg-purple-100 text-purple-600 dark:bg-purple-900/30'}`}
                    >
                        {isApi ? <Link2 className="h-4 w-4" /> : <Puzzle className="h-4 w-4" />}
                    </span>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{isApi ? 'API (OAuth)' : 'Browser Extension'}</span>
                            {connectionMethod.requires_business_account && (
                                <Badge variant="outline" className="text-xs">
                                    <Building2 className="mr-1 h-3 w-3" />
                                    Business
                                </Badge>
                            )}
                            {connectionMethod.requires_paid_api && (
                                <Badge variant="outline" className="text-xs">
                                    <Crown className="mr-1 h-3 w-3" />
                                    Paid API
                                </Badge>
                            )}
                        </div>
                        {connectionMethod.notes && <p className="text-muted-foreground mt-0.5 text-xs">{connectionMethod.notes}</p>}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {isConnected ? (
                        <>
                            <div className="mr-2 text-right">
                                <p className="text-sm font-medium">@{connection.platform_username}</p>
                                {connection.is_token_expired && <p className="text-xs text-amber-600">Token expired</p>}
                                {connection.auto_moderation_enabled && !connection.is_token_expired && (
                                    <p className="text-xs text-green-600">Auto moderation on</p>
                                )}
                            </div>
                            <Button variant="outline" size="sm" onClick={onSettings}>
                                <Settings className="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="sm" onClick={onDisconnect}>
                                <Unlink className="h-4 w-4" />
                            </Button>
                        </>
                    ) : canAccess ? (
                        <Button size="sm" onClick={onConnect}>
                            <Link2 className="mr-2 h-4 w-4" />
                            Connect
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('subscription.plans')}>
                                <Lock className="mr-2 h-4 w-4" />
                                Upgrade to Access
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {isConnected && connection.last_scanned_at && (
                <div className="text-muted-foreground mt-3 flex items-center gap-2 border-t pt-3 text-xs">
                    <Clock className="h-3 w-3" />
                    <span>Last scan: {new Date(connection.last_scanned_at).toLocaleString()}</span>
                </div>
            )}
        </div>
    );
}
