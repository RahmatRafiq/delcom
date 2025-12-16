import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Globe2, Lock } from 'lucide-react';
import Heading from '../../components/heading';

interface GalleryHeaderProps {
    currentVisibility: 'public' | 'private';
}

export default function GalleryHeader({ currentVisibility }: GalleryHeaderProps) {
    const handleVisibilityChange = (newVisibility: 'public' | 'private') => {
        router.get(
            route('gallery.index'),
            { visibility: newVisibility },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <div className="mb-4 flex items-center justify-between">
            <div>
                <Heading title="File Manager" />
                <p className="text-muted-foreground">Manage public and private files in your application</p>
            </div>
            <div className="flex gap-2">
                <Button variant={currentVisibility === 'public' ? 'default' : 'outline'} size="sm" onClick={() => handleVisibilityChange('public')}>
                    <Globe2 className="mr-2 h-4 w-4" />
                    Public Files
                </Button>
                <Button variant={currentVisibility === 'private' ? 'default' : 'outline'} size="sm" onClick={() => handleVisibilityChange('private')}>
                    <Lock className="mr-2 h-4 w-4" />
                    Private Files
                </Button>
            </div>
        </div>
    );
}
