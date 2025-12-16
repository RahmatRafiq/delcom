export interface MediaItem {
    id: number;
    file_name: string;
    name: string;
    original_url: string;
    disk: string;
    collection_name: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface FilemanagerFolder {
    id: number;
    name: string;
    parent_id: number | null;
    path?: string | null;
}

export interface GalleryProps {
    media: {
        data: MediaItem[];
        links?: PaginationLink[];
    };
    visibility: 'public' | 'private';
    collections: string[];
    selected_collection?: string | null;
}
