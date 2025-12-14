import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import CustomSelect from '@/components/select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, FilterGroup, Platform } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    filterGroup?: FilterGroup;
    platforms: Platform[];
}

export default function FilterGroupForm({ filterGroup, platforms }: Props) {
    const isEdit = !!filterGroup;

    const { data, setData, post, put, processing, errors } = useForm({
        name: filterGroup?.name ?? '',
        description: filterGroup?.description ?? '',
        is_active: filterGroup?.is_active ?? true,
        applies_to_platforms: filterGroup?.applies_to_platforms ?? [],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Filter Groups', href: route('filter-groups.index') },
        { title: isEdit ? 'Edit Group' : 'Create Group', href: '#' },
    ];

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            put(route('filter-groups.update', filterGroup!.id));
        } else {
            post(route('filter-groups.store'));
        }
    };

    const platformOptions = platforms.map((p) => ({
        value: p.name,
        label: p.display_name,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Filter Group' : 'Create Filter Group'} />
            <div className="mx-auto max-w-2xl px-4 py-6">
                <HeadingSmall
                    title={isEdit ? 'Edit Filter Group' : 'Create Filter Group'}
                    description="Filter groups help you organize your moderation filters"
                />
                <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="name">Group Name</Label>
                        <Input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g., Spam Filters, Hate Speech, etc."
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Description (Optional)</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Describe what this filter group is for..."
                            rows={3}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="platforms">Apply to Platforms</Label>
                        <p className="text-muted-foreground text-sm">Leave empty to apply to all platforms</p>
                        <CustomSelect
                            id="platforms"
                            isMulti
                            options={platformOptions}
                            value={platformOptions.filter((option) => data.applies_to_platforms?.includes(option.value))}
                            onChange={(newValue) =>
                                setData(
                                    'applies_to_platforms',
                                    Array.isArray(newValue) ? newValue.map((option) => option.value) : [],
                                )
                            }
                            placeholder="Select platforms (optional)"
                        />
                        <InputError message={errors.applies_to_platforms} />
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', checked === true)}
                        />
                        <Label htmlFor="is_active" className="cursor-pointer">
                            Active
                        </Label>
                        <span className="text-muted-foreground text-sm">(Inactive groups won't be used for moderation)</span>
                    </div>

                    <div className="flex items-center gap-4 pt-4">
                        <Button disabled={processing}>{isEdit ? 'Update Group' : 'Create Group'}</Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('filter-groups.index')}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
