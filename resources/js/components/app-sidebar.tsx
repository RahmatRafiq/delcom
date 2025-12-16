import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    // Accessibility
    Accessibility,
    Activity,
    AlertCircle,
    AlertOctagon,
    AlertTriangle,
    AlignCenter,
    AlignJustify,
    // Text & Typography
    AlignLeft,
    AlignRight,
    Archive,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    // Navigation
    ArrowUp,
    Award,
    Badge,
    // Quality & Rating
    BadgeCheck,
    BarChart,
    BarChart2,
    BarChart3,
    BarChart4,
    Bell,
    BellOff,
    Bike,
    Bold,
    Bookmark,
    Briefcase,
    Brush,
    Bug,
    // Business & Finance
    Building,
    Building2,
    Bus,
    Calculator,
    Calendar,
    CalendarCheck,
    // Time & Date
    CalendarDays,
    CalendarX,
    Camera,
    // Transportation
    Car,
    Check,
    CheckCircle,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsDown,
    ChevronsLeft,
    ChevronsRight,
    ChevronsUp,
    ChevronUp,
    // Shapes & Design
    Circle,
    Clock,
    Cloud,
    CloudRain,
    // Development
    Code,
    Code2,
    // Food & Drink
    Coffee,
    // System & Settings
    Cog,
    Compass,
    Contact,
    Cookie,
    CreditCard,
    Cross,
    Crosshair,
    Crown,
    Database,
    Diamond,
    DollarSign,
    Download,
    // Edit & Content
    Edit,
    Edit2,
    Edit3,
    Eye,
    EyeOff,
    File,
    FileEdit,
    FileInput,
    FileOutput,
    FilePlus,
    Files,
    FileText,
    FileX,
    Film,
    Filter,
    Fingerprint,
    Flame,
    // Nature
    Flower,
    Focus,
    Folder,
    FolderKanban,
    FolderOpen,
    // Gaming & Entertainment
    Gamepad,
    Gamepad2,
    Gem,
    // Miscellaneous
    Gift,
    GitBranch,
    GitCommit,
    Github,
    GitMerge,
    Glasses,
    Globe,
    Grid3X3,
    Hammer,
    Hand,
    HardDrive,
    Heart,
    HeartHandshake,
    HelpCircle,
    Hexagon,
    Home,
    Hourglass,
    // Media & Files
    Image,
    Import,
    Info,
    Italic,
    // Organization
    Kanban,
    Key,
    KeyRound,
    Laptop,
    Layers,
    LayoutDashboard,
    Leaf,
    Lightbulb,
    LineChart,
    Link2,
    List,
    ListChecks,
    ListOrdered,
    // Status & Indicators
    Loader,
    Loader2,
    Lock,
    // Communication
    Mail,
    MailOpen,
    // Location & Map
    Map,
    MapPin,
    Medal,
    // Navigation & Layout
    Menu,
    MessageCircle,
    MessageSquare,
    MessageSquareX,
    Mic,
    MicOff,
    Minus,
    MinusCircle,
    // Technology
    Monitor,
    Moon,
    MousePointer,
    MousePointer2,
    // Movement & Direction
    Move,
    MoveHorizontal,
    MoveVertical,
    Music,
    Navigation,
    Navigation2,
    Package,
    Package2,
    Palette,
    Pen,
    PenTool,
    Phone,
    PhoneCall,
    PieChart,
    Pill,
    Pizza,
    Plane,
    // Actions
    Plus,
    PlusCircle,
    Power,
    // Print & Export
    Printer,
    Radar,
    RefreshCcw,
    RotateCcw,
    RotateCw,
    Scan,
    ScrollText,
    // Tools & Utilities
    Search,
    Send,
    Server,
    Settings,
    // Social & Sharing
    Share,
    Share2,
    Shield,
    ShieldAlert,
    // Security
    ShieldCheck,
    ShieldX,
    ShoppingBag,
    // Shopping & E-commerce
    ShoppingCart,
    Sidebar as SidebarIcon,
    Sliders,
    Smartphone,
    Snowflake,
    Sparkles,
    Square,
    Star,
    // Health & Medical
    Stethoscope,
    Store,
    // Weather
    Sun,
    Tablet,
    Tag,
    Tags,
    // Analytics
    Target,
    Terminal,
    Thermometer,
    ThumbsDown,
    ThumbsUp,
    Timer,
    Train,
    Trees,
    TrendingDown,
    TrendingUp,
    Triangle,
    Trophy,
    Truck,
    Type,
    Umbrella,
    Underline,
    Unlock,
    Upload,
    // User & Profile
    User,
    User2,
    UserCheck,
    UserCog,
    UserMinus,
    UserPlus,
    Users,
    UsersRound,
    UserX,
    Verified,
    Video,
    Volume,
    Volume1,
    Volume2,
    VolumeOff,
    VolumeX,
    Wallet,
    Watch,
    Wifi,
    WifiOff,
    Wine,
    Workflow,
    Wrench,
    X,
    XCircle,
    XCircle as XCircleIcon,
    Zap,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from './app-logo';

type MenuItem = {
    id: number;
    title: string;
    route?: string | null;
    icon?: string | null;
    permission?: string | null;
    parent_id?: number | null;
    order?: number;
    children?: MenuItem[];
};

const iconMap: Record<string, LucideIcon> = {
    ListChecks,
    // Original icons
    LayoutDashboard,
    Activity,
    Users,
    Shield,
    Key,
    UserCheck,
    Settings,
    FileText,
    Github,

    // Navigation & Layout
    Menu,
    Home,
    Archive,
    Folder,
    FolderOpen,
    FolderKanban,
    Grid3X3,
    Layers,
    Navigation,
    SidebarIcon,

    // User & Profile
    User,
    UserPlus,
    UserMinus,
    UserX,
    UserCog,
    User2,
    UsersRound,
    Contact,

    // Business & Finance
    Building,
    Building2,
    Briefcase,
    Calculator,
    CreditCard,
    DollarSign,
    Wallet,
    TrendingUp,
    TrendingDown,
    BarChart,
    BarChart2,
    BarChart3,
    BarChart4,
    LineChart,
    PieChart,

    // Communication
    Mail,
    MailOpen,
    MessageCircle,
    MessageSquare,
    MessageSquareX,
    Send,
    Phone,
    PhoneCall,
    Video,
    Mic,
    MicOff,

    // Media & Files
    Image,
    Camera,
    Film,
    Music,
    Upload,
    Download,
    File,
    Files,
    FilePlus,
    FileX,
    FileEdit,

    // Tools & Utilities
    Search,
    Filter,
    Calendar,
    Clock,
    Timer,
    Bookmark,
    Tag,
    Link2,
    Tags,
    Bell,
    BellOff,

    // Actions
    Plus,
    Minus,
    X,
    Check,
    CheckCircle,
    XCircle,
    AlertCircle,
    AlertTriangle,
    Info,
    HelpCircle,

    // Navigation
    ArrowUp,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ChevronUp,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUp,
    ChevronsDown,
    ChevronsLeft,
    ChevronsRight,

    // Edit & Content
    Edit,
    Edit2,
    Edit3,
    Pen,
    PenTool,
    Type,
    Bold,
    Italic,
    Underline,

    // System & Settings
    Cog,
    Sliders,
    Power,
    RefreshCcw,
    RotateCcw,
    RotateCw,
    Lock,
    Unlock,
    Eye,
    EyeOff,

    // Shopping & E-commerce
    ShoppingCart,
    ShoppingBag,
    Store,
    Package,
    Package2,
    Truck,

    // Technology
    Monitor,
    Smartphone,
    Tablet,
    Laptop,
    Server,
    Database,
    HardDrive,
    Wifi,
    WifiOff,

    // Social & Sharing
    Share,
    Share2,
    Heart,
    HeartHandshake,
    Star,
    ThumbsUp,
    ThumbsDown,

    // Location & Map
    Map,
    MapPin,
    Navigation2,
    Compass,
    Globe,

    // Weather
    Sun,
    Moon,
    Cloud,
    CloudRain,
    Zap,

    // Health & Medical
    Stethoscope,
    Pill,
    Cross,
    Thermometer,

    // Gaming & Entertainment
    Gamepad,
    Gamepad2,
    Trophy,
    Award,
    Medal,

    // Transportation
    Car,
    Plane,
    Train,
    Bike,
    Bus,

    // Food & Drink
    Coffee,
    Cookie,
    Pizza,
    Wine,

    // Nature
    Flower,
    Trees,
    Leaf,

    // Miscellaneous
    Gift,
    Lightbulb,
    Flame,
    Snowflake,
    Umbrella,
    Glasses,
    Crown,
    Gem,
    Palette,
    Brush,
    Sparkles,
    ScrollText,

    // Development
    Code,
    Code2,
    Terminal,
    Bug,
    Wrench,
    Hammer,

    // Analytics
    Target,
    Focus,
    Crosshair,
    Radar,

    // Security
    ShieldCheck,
    ShieldAlert,
    ShieldX,
    KeyRound,
    Fingerprint,
    Scan,

    // Time & Date
    CalendarDays,
    CalendarCheck,
    CalendarX,
    Watch,
    Hourglass,

    // Text & Typography
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    List,
    ListOrdered,

    // Shapes & Design
    Circle,
    Square,
    Triangle,
    Diamond,
    Hexagon,

    // Status & Indicators
    Loader,
    Loader2,
    CheckCircle2,
    XCircleIcon,
    AlertOctagon,
    MinusCircle,
    PlusCircle,

    // Movement & Direction
    Move,
    MoveHorizontal,
    MoveVertical,
    MousePointer,
    MousePointer2,
    Hand,

    // Organization
    Kanban,
    GitBranch,
    GitCommit,
    GitMerge,
    Workflow,

    // Print & Export
    Printer,
    FileOutput,
    FileInput,
    Import,

    // Quality & Rating
    BadgeCheck,
    Badge,
    Verified,

    // Accessibility
    Accessibility,
    Volume,
    Volume1,
    Volume2,
    VolumeOff,
    VolumeX,
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Github,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: FileText,
    },
];

function mapMenuToNavItem(menu: MenuItem): NavItem {
    return {
        title: menu.title,
        href: menu.route ? route(menu.route) : '#',
        icon: menu.icon && iconMap[menu.icon] ? iconMap[menu.icon] : undefined,
        children: menu.children ? menu.children.map(mapMenuToNavItem) : undefined,
    };
}

export function AppSidebar() {
    const { sidebarMenus = [] } = usePage().props as { sidebarMenus?: MenuItem[] };
    const navItems = (sidebarMenus ?? []).map(mapMenuToNavItem);
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
