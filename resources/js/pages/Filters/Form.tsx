import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import CustomSelect from '@/components/select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, Filter, FilterAction, FilterGroup, FilterMatchType, FilterType } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useState } from 'react';

interface Props {
    filter?: Filter;
    filterGroups: FilterGroup[];
    selectedGroupId?: string | null;
}

const typeOptions = [
    { value: 'keyword', label: 'Keyword', description: 'Match specific words or short phrases' },
    { value: 'phrase', label: 'Phrase', description: 'Match longer text sequences' },
    { value: 'regex', label: 'Regular Expression', description: 'Advanced pattern matching' },
    { value: 'username', label: 'Username', description: 'Match specific usernames' },
    { value: 'url', label: 'URL Pattern', description: 'Match URLs with wildcards' },
    { value: 'emoji_spam', label: 'Emoji Spam', description: 'Detect excessive emoji usage (pattern = threshold count)' },
    { value: 'repeat_char', label: 'Repeated Characters', description: 'Detect repeated characters like "aaaaaa" (pattern = threshold)' },
];

const matchTypeOptions = [
    { value: 'contains', label: 'Contains', description: 'Pattern appears anywhere in text' },
    { value: 'exact', label: 'Exact Match', description: 'Text must match pattern exactly' },
    { value: 'starts_with', label: 'Starts With', description: 'Text begins with pattern' },
    { value: 'ends_with', label: 'Ends With', description: 'Text ends with pattern' },
    { value: 'regex', label: 'Regex', description: 'Use pattern as regular expression' },
];

const actionOptions = [
    { value: 'delete', label: 'Delete', description: 'Permanently delete the comment' },
    { value: 'hide', label: 'Hide', description: 'Hide comment from public view' },
    { value: 'flag', label: 'Flag for Review', description: 'Mark for manual review' },
    { value: 'report', label: 'Report to Platform', description: 'Report to platform moderation' },
];

export default function FilterForm({ filter, filterGroups, selectedGroupId }: Props) {
    const isEdit = !!filter;
    const [testText, setTestText] = useState('');
    const [testResult, setTestResult] = useState<boolean | null>(null);
    const [testing, setTesting] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        filter_group_id: filter?.filter_group_id ?? (selectedGroupId ? parseInt(selectedGroupId) : ''),
        type: filter?.type ?? 'keyword',
        pattern: filter?.pattern ?? '',
        match_type: filter?.match_type ?? 'contains',
        case_sensitive: filter?.case_sensitive ?? false,
        action: filter?.action ?? 'delete',
        priority: filter?.priority ?? 0,
        is_active: filter?.is_active ?? true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Filter Groups', href: route('filter-groups.index') },
        { title: 'Filters', href: route('filters.index') },
        { title: isEdit ? 'Edit Filter' : 'Create Filter', href: '#' },
    ];

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            put(route('filters.update', filter!.id));
        } else {
            post(route('filters.store'));
        }
    };

    const handleTest = async () => {
        if (!testText || !data.pattern) return;

        setTesting(true);
        setTestResult(null);

        try {
            const response = await axios.post(route('filters.test-pattern'), {
                type: data.type,
                pattern: data.pattern,
                match_type: data.match_type,
                case_sensitive: data.case_sensitive,
                test_text: testText,
            });
            setTestResult(response.data.matches);
        } catch (error) {
            console.error('Test failed:', error);
        } finally {
            setTesting(false);
        }
    };

    const groupOptions = filterGroups.map((g) => ({
        value: g.id,
        label: g.name,
    }));

    // Show simplified UI for special filter types
    const isSpecialType = ['emoji_spam', 'repeat_char'].includes(data.type);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Filter' : 'Create Filter'} />
            <div className="mx-auto max-w-2xl px-4 py-6">
                <HeadingSmall
                    title={isEdit ? 'Edit Filter' : 'Create Filter'}
                    description="Define a pattern to automatically moderate comments"
                />
                <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="filter_group_id">Filter Group</Label>
                        <CustomSelect
                            id="filter_group_id"
                            options={groupOptions}
                            value={groupOptions.find((o) => o.value === data.filter_group_id)}
                            onChange={(selected) => setData('filter_group_id', (selected as { value: number })?.value ?? '')}
                            placeholder="Select a filter group"
                        />
                        <InputError message={errors.filter_group_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="type">Filter Type</Label>
                        <CustomSelect
                            id="type"
                            options={typeOptions}
                            value={typeOptions.find((o) => o.value === data.type)}
                            onChange={(selected) => setData('type', ((selected as { value: string })?.value ?? 'keyword') as FilterType)}
                            formatOptionLabel={(option: { value: string; label: string; description?: string }) => (
                                <div>
                                    <div className="font-medium">{option.label}</div>
                                    {option.description && <div className="text-muted-foreground text-xs">{option.description}</div>}
                                </div>
                            )}
                        />
                        <InputError message={errors.type} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="pattern">
                            {isSpecialType ? 'Threshold (number)' : 'Pattern'}
                        </Label>
                        {isSpecialType ? (
                            <Input
                                id="pattern"
                                type="number"
                                min="1"
                                max="100"
                                value={data.pattern}
                                onChange={(e) => setData('pattern', e.target.value)}
                                placeholder={data.type === 'emoji_spam' ? 'e.g., 10 (trigger when 10+ emojis)' : 'e.g., 5 (trigger when 5+ repeated chars)'}
                                required
                            />
                        ) : (
                            <Textarea
                                id="pattern"
                                value={data.pattern}
                                onChange={(e) => setData('pattern', e.target.value)}
                                placeholder={data.type === 'regex' ? 'e.g., (slot|togel)\\s*gacor' : 'e.g., slot gacor'}
                                rows={2}
                                required
                            />
                        )}
                        <InputError message={errors.pattern} />
                    </div>

                    {!isSpecialType && (
                        <div className="space-y-2">
                            <Label htmlFor="match_type">Match Type</Label>
                            <CustomSelect
                                id="match_type"
                                options={matchTypeOptions}
                                value={matchTypeOptions.find((o) => o.value === data.match_type)}
                                onChange={(selected) => setData('match_type', ((selected as { value: string })?.value ?? 'contains') as FilterMatchType)}
                                formatOptionLabel={(option: { value: string; label: string; description?: string }) => (
                                    <div>
                                        <div className="font-medium">{option.label}</div>
                                        {option.description && <div className="text-muted-foreground text-xs">{option.description}</div>}
                                    </div>
                                )}
                            />
                            <InputError message={errors.match_type} />
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="action">Action</Label>
                        <CustomSelect
                            id="action"
                            options={actionOptions}
                            value={actionOptions.find((o) => o.value === data.action)}
                            onChange={(selected) => setData('action', ((selected as { value: string })?.value ?? 'delete') as FilterAction)}
                            formatOptionLabel={(option: { value: string; label: string; description?: string }) => (
                                <div>
                                    <div className="font-medium">{option.label}</div>
                                    {option.description && <div className="text-muted-foreground text-xs">{option.description}</div>}
                                </div>
                            )}
                        />
                        <InputError message={errors.action} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="priority">Priority (0-100)</Label>
                        <Input
                            id="priority"
                            type="number"
                            min="0"
                            max="100"
                            value={data.priority}
                            onChange={(e) => setData('priority', parseInt(e.target.value) || 0)}
                        />
                        <p className="text-muted-foreground text-xs">Higher priority filters are checked first</p>
                        <InputError message={errors.priority} />
                    </div>

                    <div className="flex flex-wrap gap-6">
                        {!isSpecialType && (
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="case_sensitive"
                                    checked={data.case_sensitive}
                                    onCheckedChange={(checked) => setData('case_sensitive', checked === true)}
                                />
                                <Label htmlFor="case_sensitive" className="cursor-pointer">
                                    Case Sensitive
                                </Label>
                            </div>
                        )}
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', checked === true)}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">
                                Active
                            </Label>
                        </div>
                    </div>

                    {/* Test Pattern Section */}
                    {!isSpecialType && (
                        <div className="rounded-lg border p-4">
                            <Label className="mb-2 block">Test Pattern</Label>
                            <div className="flex gap-2">
                                <Textarea
                                    value={testText}
                                    onChange={(e) => {
                                        setTestText(e.target.value);
                                        setTestResult(null);
                                    }}
                                    placeholder="Enter sample text to test your pattern..."
                                    rows={2}
                                    className="flex-1"
                                />
                                <Button type="button" variant="outline" onClick={handleTest} disabled={testing || !testText || !data.pattern}>
                                    {testing ? 'Testing...' : 'Test'}
                                </Button>
                            </div>
                            {testResult !== null && (
                                <div className={`mt-2 rounded p-2 text-sm ${testResult ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'}`}>
                                    {testResult ? 'Pattern matches the test text' : 'Pattern does not match the test text'}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-4 pt-4">
                        <Button disabled={processing}>{isEdit ? 'Update Filter' : 'Create Filter'}</Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('filters.index', data.filter_group_id ? { group: data.filter_group_id } : {})}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
