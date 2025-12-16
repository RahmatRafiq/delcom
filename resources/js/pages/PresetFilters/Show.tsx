import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, PresetFilter, PresetFilterData } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';

interface Props {
    preset: PresetFilter;
}

const typeLabels: Record<string, string> = {
    keyword: 'Keyword',
    phrase: 'Phrase',
    regex: 'Regex',
    username: 'Username',
    url: 'URL',
    emoji_spam: 'Emoji Spam',
    repeat_char: 'Repeat Char',
};

const matchTypeLabels: Record<string, string> = {
    exact: 'Exact',
    contains: 'Contains',
    starts_with: 'Starts With',
    ends_with: 'Ends With',
    regex: 'Regex',
};

const actionLabels: Record<string, { label: string; class: string }> = {
    delete: { label: 'Delete', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    hide: { label: 'Hide', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
    flag: { label: 'Flag', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    report: { label: 'Report', class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
};

export default function PresetFilterShow({ preset }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Filter Groups', href: route('filter-groups.index') },
        { title: 'Preset Filters', href: route('preset-filters.index') },
        { title: preset.name, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Preset: ${preset.name}`} />
            <PageContainer maxWidth="4xl">
                <Heading title="Preset Filter Details" />

                <Card className="mb-6">
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div>
                                <CardTitle className="text-2xl">{preset.name}</CardTitle>
                                <p className="text-muted-foreground mt-1">{preset.description}</p>
                            </div>
                            <Badge variant="secondary" className="text-sm">
                                {preset.category}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-4">
                            <div className="text-muted-foreground">
                                <span className="text-foreground font-semibold">{preset.filters_data.length}</span> filter patterns
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <HeadingSmall title="Filter Patterns" description="All patterns included in this preset" />

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[100px]">Type</TableHead>
                                <TableHead>Pattern</TableHead>
                                <TableHead className="w-[120px]">Match Type</TableHead>
                                <TableHead className="w-[100px]">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {preset.filters_data.map((filter: PresetFilterData, index: number) => {
                                const action = filter.action || 'delete';
                                const actionConfig = actionLabels[action] || actionLabels.delete;

                                return (
                                    <TableRow key={index}>
                                        <TableCell>
                                            <Badge variant="outline">{typeLabels[filter.type] || filter.type}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <code className="bg-muted rounded px-2 py-1 text-sm">{filter.pattern}</code>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-muted-foreground text-sm">
                                                {matchTypeLabels[filter.match_type] || filter.match_type}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium ${actionConfig.class}`}
                                            >
                                                {actionConfig.label}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                <div className="mt-6 flex items-center gap-4">
                    <Link href={route('preset-filters.index')}>
                        <Button variant="outline">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Presets
                        </Button>
                    </Link>
                    <Link href={route('preset-filters.index')}>
                        <Button>
                            <Download className="mr-2 h-4 w-4" />
                            Import This Preset
                        </Button>
                    </Link>
                </div>
            </PageContainer>
        </AppLayout>
    );
}
