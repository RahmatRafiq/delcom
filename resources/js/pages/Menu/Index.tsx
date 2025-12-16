import ConfirmationDialog from '@/components/confirmation-dialog';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import PageContainer from '@/components/page-container';
import TreeDnD from '@/components/TreeDnD';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/hooks/use-confirmation';
import AppLayout from '@/layouts/app-layout';
import { toast } from '@/utils/toast';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import React from 'react';

export interface MenuTreeItem {
    id: number;
    title: string;
    route?: string | null;
    icon?: string | null;
    permission?: string | null;
    parent_id?: number | null;
    order?: number;
    children?: MenuTreeItem[];
}

function MenuIndexPage() {
    const { confirmationState, openConfirmation, handleConfirm, handleCancel } = useConfirmation();
    const handleDeleteMenu = (id: number) => {
        openConfirmation({
            title: 'Delete Menu Confirmation',
            message: 'Are you sure you want to delete this menu? This action cannot be undone.',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            variant: 'destructive',
            icon: <Trash2 className="h-6 w-6 text-red-600" />,
            onConfirm: () => {
                router.delete(route('menus.destroy', id), {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        if (typeof page.props.success === 'string') toast.success(page.props.success);
                        else toast.success('Menu deleted successfully.');
                    },
                    onError: () => {
                        toast.error('Failed to delete menu.');
                    },
                });
            },
        });
    };
    const renderMenuItem = (item: MenuTreeItem) => (
        <div className="border-border bg-background hover:bg-accent/20 group mb-2 flex min-h-[48px] items-center gap-3 rounded-lg border px-4 py-3 shadow-sm transition-all">
            <span className="text-muted-foreground cursor-move text-xl select-none">≡</span>
            <span className="text-foreground max-w-[180px] truncate text-base font-semibold">{item.title}</span>
            {item.route && <span className="text-muted-foreground ml-1 text-xs">({item.route})</span>}
            {item.permission && <span className="bg-muted ml-1 rounded px-2 py-0.5 text-xs">{item.permission}</span>}
            <div className="flex-1" />
            <Link href={route('menus.edit', item.id)} title="Edit Menu" className="ml-2">
                <Button type="button" size="icon" variant="outline" className="h-8 w-8" aria-label="Edit Menu">
                    <Pencil size={16} />
                </Button>
            </Link>
            <Button
                type="button"
                size="icon"
                variant="outline"
                className="ml-2 h-8 w-8 text-red-600 hover:bg-red-50"
                title="Delete Menu"
                aria-label="Delete Menu"
                onClick={() => handleDeleteMenu(item.id)}
            >
                <Trash2 size={16} />
            </Button>
        </div>
    );
    const { menus, success } = usePage().props as unknown as { menus: MenuTreeItem[]; success?: string };
    const [tree, setTree] = React.useState<MenuTreeItem[]>(menus);
    const [saving, setSaving] = React.useState(false);

    React.useEffect(() => {
        setTree(menus);
    }, [menus]);

    function normalizeTree(items: MenuTreeItem[]): MenuTreeItem[] {
        return items.map((item) => ({
            ...item,
            children: Array.isArray(item.children) && item.children.length > 0 ? normalizeTree(item.children) : [],
        }));
    }

    const handleSaveOrder = async () => {
        setSaving(true);
        try {
            const normalized = normalizeTree(tree);
            await router.post(
                route('menus.updateOrder'),
                { tree: JSON.stringify(normalized) },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: (page) => {
                        if (typeof page.props.success === 'string') toast.success(page.props.success);
                        else toast.success('Menu order saved successfully.');
                    },
                    onError: () => {
                        toast.error('Failed to save menu order.');
                    },
                    onFinish: () => {
                        setSaving(false);
                    },
                    only: [],
                },
            );
        } catch {
            toast.error('Failed to save menu order.');
            setSaving(false);
        }
    };
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Menu Management', href: '#' },
            ]}
        >
            <Head title="Menu Management" />
            <PageContainer maxWidth="2xl">
                <Heading title="Menu Management" description="Manage your application's navigation menu structure." />
                <div className="mb-4 flex items-center justify-between">
                    <HeadingSmall title="Menu List" description="View and organize your application's menus." />
                    <Link href={route('menus.create')} className="ml-4">
                        <Button type="button" size="sm" className="font-semibold">
                            + Add Menu
                        </Button>
                    </Link>
                </div>
                {success && <div className="mb-2 text-sm font-medium text-green-600">{success}</div>}
                <div className="mb-2 flex justify-end">
                    <Button type="button" size="sm" className="flex items-center gap-2" onClick={handleSaveOrder} disabled={saving}>
                        {saving && (
                            <svg className="mr-1 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        )}
                        {saving ? 'Saving Order...' : 'Save Order'}
                    </Button>
                </div>
                <div className="dark:bg-card border-border mt-2 rounded border bg-white p-4 shadow">
                    <TreeDnD
                        items={tree}
                        onChange={setTree}
                        getId={(item) => item.id}
                        getChildren={(item) => item.children}
                        setChildren={(item, children) => ({ ...item, children })}
                        renderItem={renderMenuItem}
                    />
                </div>
                <ConfirmationDialog state={confirmationState} onConfirm={handleConfirm} onCancel={handleCancel} />
            </PageContainer>
        </AppLayout>
    );
}

export default MenuIndexPage;
