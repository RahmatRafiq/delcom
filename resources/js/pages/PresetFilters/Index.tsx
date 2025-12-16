import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import CustomSelect from '@/components/select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, FilterGroup, PresetFilter } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Download, Filter, Megaphone, Shield, Skull } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    presets: PresetFilter[];
    groupedPresets: Record<string, PresetFilter[]>;
    categories: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Filter Groups', href: route('filter-groups.index') },
    { title: 'Preset Filters', href: route('preset-filters.index') },
];

const categoryIcons: Record<string, React.ReactNode> = {
    spam: <AlertTriangle className="h-5 w-5" />,
    self_promotion: <Megaphone className="h-5 w-5" />,
    scam: <Skull className="h-5 w-5" />,
    hate_speech: <Shield className="h-5 w-5" />,
    other: <Filter className="h-5 w-5" />,
};

const categoryColors: Record<string, string> = {
    spam: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    self_promotion: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    scam: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    hate_speech: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    other: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
};

export default function PresetFiltersIndex({ groupedPresets, categories }: Props) {
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [selectedPreset, setSelectedPreset] = useState<PresetFilter | null>(null);
    const [userGroups, setUserGroups] = useState<FilterGroup[]>([]);
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);
    const [newGroupName, setNewGroupName] = useState('');
    const [importing, setImporting] = useState(false);
    const [createNewGroup, setCreateNewGroup] = useState(true);

    useEffect(() => {
        // Fetch user's filter groups when dialog opens
        if (importDialogOpen) {
            axios.get(route('api.user-filter-groups')).then((response) => {
                setUserGroups(response.data);
            });
        }
    }, [importDialogOpen]);

    const handleImportClick = (preset: PresetFilter) => {
        setSelectedPreset(preset);
        setNewGroupName(preset.name);
        setCreateNewGroup(true);
        setSelectedGroupId(null);
        setImportDialogOpen(true);
    };

    const handleImport = () => {
        if (!selectedPreset) return;

        setImporting(true);

        const data = createNewGroup ? { new_group_name: newGroupName } : { filter_group_id: selectedGroupId };

        router.post(route('preset-filters.import', selectedPreset.id), data, {
            onSuccess: () => {
                setImportDialogOpen(false);
                setSelectedPreset(null);
            },
            onFinish: () => {
                setImporting(false);
            },
        });
    };

    const groupOptions = userGroups.map((g) => ({
        value: g.id,
        label: g.name,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Preset Filters" />
            <PageContainer maxWidth="7xl">
                <Heading title="Comment Moderation" />
                <HeadingSmall
                    title="Preset Filters"
                    description="Pre-configured filter sets for common spam patterns. Import them to get started quickly."
                />

                <div className="mb-6 flex items-center gap-4">
                    <Link href={route('filter-groups.index')}>
                        <Button variant="outline">Back to Filter Groups</Button>
                    </Link>
                </div>

                {Object.entries(groupedPresets).map(([category, categoryPresets]) => (
                    <div key={category} className="mb-8">
                        <div className="mb-4 flex items-center gap-2">
                            <span className={`rounded-full p-2 ${categoryColors[category] || categoryColors.other}`}>
                                {categoryIcons[category] || categoryIcons.other}
                            </span>
                            <h2 className="text-xl font-semibold">{categories[category] || category}</h2>
                            <Badge variant="secondary">{categoryPresets.length} presets</Badge>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {categoryPresets.map((preset) => (
                                <Card key={preset.id} className="flex flex-col">
                                    <CardHeader>
                                        <CardTitle className="text-lg">{preset.name}</CardTitle>
                                        <CardDescription>{preset.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex-1">
                                        <div className="text-muted-foreground text-sm">
                                            <span className="text-foreground font-medium">{preset.filters_data.length}</span> filter patterns
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {preset.filters_data.slice(0, 5).map((f, i) => (
                                                <code key={i} className="bg-muted rounded px-1.5 py-0.5 text-xs">
                                                    {f.pattern.length > 15 ? f.pattern.substring(0, 15) + '...' : f.pattern}
                                                </code>
                                            ))}
                                            {preset.filters_data.length > 5 && (
                                                <span className="text-muted-foreground text-xs">+{preset.filters_data.length - 5} more</span>
                                            )}
                                        </div>
                                    </CardContent>
                                    <CardFooter className="flex gap-2">
                                        <Link href={route('preset-filters.show', preset.id)} className="flex-1">
                                            <Button variant="outline" className="w-full">
                                                View Details
                                            </Button>
                                        </Link>
                                        <Button onClick={() => handleImportClick(preset)} className="flex-1">
                                            <Download className="mr-2 h-4 w-4" />
                                            Import
                                        </Button>
                                    </CardFooter>
                                </Card>
                            ))}
                        </div>
                    </div>
                ))}

                {/* Import Dialog */}
                <Dialog open={importDialogOpen} onOpenChange={setImportDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Import Preset: {selectedPreset?.name}</DialogTitle>
                            <DialogDescription>
                                Choose where to import the {selectedPreset?.filters_data.length} filters from this preset.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="flex items-center space-x-4">
                                <Button
                                    type="button"
                                    variant={createNewGroup ? 'default' : 'outline'}
                                    onClick={() => setCreateNewGroup(true)}
                                    className="flex-1"
                                >
                                    Create New Group
                                </Button>
                                <Button
                                    type="button"
                                    variant={!createNewGroup ? 'default' : 'outline'}
                                    onClick={() => setCreateNewGroup(false)}
                                    disabled={userGroups.length === 0}
                                    className="flex-1"
                                >
                                    Add to Existing
                                </Button>
                            </div>

                            {createNewGroup ? (
                                <div className="space-y-2">
                                    <Label htmlFor="new_group_name">New Group Name</Label>
                                    <Input
                                        id="new_group_name"
                                        value={newGroupName}
                                        onChange={(e) => setNewGroupName(e.target.value)}
                                        placeholder="Enter group name"
                                    />
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="existing_group">Select Group</Label>
                                    <CustomSelect
                                        id="existing_group"
                                        options={groupOptions}
                                        value={groupOptions.find((o) => o.value === selectedGroupId)}
                                        onChange={(selected) => setSelectedGroupId((selected as { value: number })?.value ?? null)}
                                        placeholder="Choose a filter group"
                                    />
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setImportDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleImport} disabled={importing || (createNewGroup ? !newGroupName : !selectedGroupId)}>
                                {importing ? 'Importing...' : 'Import Filters'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageContainer>
        </AppLayout>
    );
}
