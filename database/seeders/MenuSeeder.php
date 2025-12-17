<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $dashboard = Menu::create([
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'LayoutDashboard',
            'order' => 1,
        ]);
        $gallery = Menu::create([
            'title' => 'Gallery',
            'route' => 'gallery.index',
            'icon' => 'FileText',
            'order' => 2,
        ]);

        // Comment Moderation
        $moderation = Menu::create([
            'title' => 'Comment Moderation',
            'icon' => 'MessageSquareX',
            'order' => 3,
        ]);
        Menu::create([
            'title' => 'Dashboard',
            'route' => 'moderation.index',
            'icon' => 'Gauge',
            'parent_id' => $moderation->id,
            'order' => 1,
        ]);
        Menu::create([
            'title' => 'Review Queue',
            'route' => 'review-queue.index',
            'icon' => 'ListChecks',
            'parent_id' => $moderation->id,
            'order' => 2,
        ]);
        Menu::create([
            'title' => 'Connected Accounts',
            'route' => 'connected-accounts.index',
            'icon' => 'Link2',
            'parent_id' => $moderation->id,
            'order' => 3,
        ]);
        Menu::create([
            'title' => 'Filter Groups',
            'route' => 'filter-groups.index',
            'icon' => 'FolderKanban',
            'parent_id' => $moderation->id,
            'order' => 4,
        ]);
        Menu::create([
            'title' => 'Filters',
            'route' => 'filters.index',
            'icon' => 'Filter',
            'parent_id' => $moderation->id,
            'order' => 5,
        ]);
        Menu::create([
            'title' => 'Preset Filters',
            'route' => 'preset-filters.index',
            'icon' => 'Sparkles',
            'parent_id' => $moderation->id,
            'order' => 6,
        ]);
        Menu::create([
            'title' => 'Moderation Logs',
            'route' => 'moderation-logs.index',
            'icon' => 'ScrollText',
            'parent_id' => $moderation->id,
            'order' => 7,
        ]);

        $users = Menu::create([
            'title' => 'Users Management',
            'icon' => 'Users',
            'order' => 4,
            'permission' => 'view-users',
        ]);
        Menu::create([
            'title' => 'Roles',
            'route' => 'roles.index',
            'icon' => 'Shield',
            'parent_id' => $users->id,
            'permission' => 'view-roles',
            'order' => 1,
        ]);
        Menu::create([
            'title' => 'Permissions',
            'route' => 'permissions.index',
            'icon' => 'Key',
            'parent_id' => $users->id,
            'permission' => 'view-permissions',
            'order' => 2,
        ]);
        Menu::create([
            'title' => 'User',
            'route' => 'users.index',
            'icon' => 'UserCheck',
            'parent_id' => $users->id,
            'permission' => 'view-users',
            'order' => 3,
        ]);
        Menu::create([
            'title' => 'App Settings',
            'route' => 'app-settings.index',
            'icon' => 'Settings',
            'permission' => 'manage-settings',
            'order' => 5,
        ]);

        Menu::create([
            'title' => 'Menu Management',
            'route' => 'menus.manage',
            'icon' => 'Settings',
            'permission' => 'manage-settings',
            'order' => 6,
        ]);
        Menu::create([
            'title' => 'Activity Logs',
            'route' => 'activity-logs.index',
            'icon' => 'ListChecks',
            'permission' => 'view-activity-logs',
            'order' => 7,
        ]);

    }
}
