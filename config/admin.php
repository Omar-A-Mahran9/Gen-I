<?php

/*
|--------------------------------------------------------------------------
| GEN-I Admin Dashboard configuration
|--------------------------------------------------------------------------
|
| Non-secret configuration for the Metronic admin. Runtime-editable values
| (application name, logos, contact details, default locale/theme) live in
| the `settings` table and override the defaults below through
| App\Services\Settings\SettingsService.
|
*/

return [

    // URL prefix and route-name prefix of the admin area.
    'prefix' => 'admin',

    /*
    | Placeholder branding. These files are neutral GEN-I placeholders, not
    | official brand assets; replace them from Admin > Settings > Branding.
    */
    'brand' => [
        'logo' => 'assets/admin/brand/logo.svg',
        'logo_dark' => 'assets/admin/brand/logo-dark.svg',
        'favicon' => 'assets/admin/brand/favicon.svg',
    ],

    // Supported locales. Arabic is the default (APP_LOCALE=ar).
    'locales' => [
        'ar' => ['name' => 'العربية', 'short' => 'ع', 'dir' => 'rtl'],
        'en' => ['name' => 'English', 'short' => 'EN', 'dir' => 'ltr'],
    ],

    /*
    | Font strategy per locale. A null family keeps the Metronic default
    | font stack. Arabic uses a self-hosted OFL font (no CDN).
    */
    'fonts' => [
        'ar' => [
            'family' => 'IBM Plex Sans Arabic',
            'faces' => [
                400 => 'assets/admin/fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf',
                600 => 'assets/admin/fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.ttf',
                700 => 'assets/admin/fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.ttf',
            ],
        ],
        'en' => [
            'family' => null,
            'faces' => [],
        ],
    ],

    'themes' => ['light', 'dark', 'system'],

    /*
    | Page-level Metronic plugins. Pages request them explicitly with
    | <x-admin.plugins :use="['datatables']" />; nothing here loads globally.
    */
    'plugins' => [
        'datatables' => [
            'css' => ['assets/admin/plugins/custom/datatables/datatables.bundle.css'],
            'css_rtl' => ['assets/admin/plugins/custom/datatables/datatables.bundle.rtl.css'],
            'js' => ['assets/admin/plugins/custom/datatables/datatables.bundle.js'],
        ],
    ],

    /*
    | Role-based access control.
    | Permissions are "<group>.<action>". Only permissions for implemented
    | foundation modules are defined; business modules add their own.
    */
    'super_admin_role' => 'super-admin',

    // Roles that cannot be renamed, edited or deleted from the UI.
    'protected_roles' => ['super-admin'],

    'permissions' => [
        'dashboard' => ['view'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
        'audit' => ['view'],
    ],

    /*
    | Initial roles seeded on first install. Pending GEN-I's approved staff
    | role catalogue (BD-14), these are foundation defaults only. Existing
    | roles are never overwritten when the seeder runs again, except
    | super-admin, which always receives every permission.
    */
    'default_roles' => [
        'super-admin' => ['*'],
        'admin' => [
            'dashboard.view',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view',
            'settings.view', 'settings.update',
            'audit.view',
        ],
        'manager' => [
            'dashboard.view',
            'users.view', 'users.create', 'users.update',
            'roles.view',
            'audit.view',
        ],
        'staff' => ['dashboard.view'],
    ],

    // Permission required to enter the admin area at all.
    'access_permission' => 'dashboard.view',

    /*
    | Sidebar navigation. Each item supports:
    |   key, label (translation key), icon (KeenIcons class), route,
    |   active (route-name patterns), permission (string|array, any-of),
    |   visible (bool), heading (section title), children (nested items).
    | Items the user cannot access are hidden, but every route still
    | enforces its own authorization on the backend.
    */
    'menu' => [
        [
            'key' => 'dashboard',
            'label' => 'admin.menu.dashboard',
            'icon' => 'ki-outline ki-element-11',
            'route' => 'admin.dashboard',
            'active' => ['admin.dashboard'],
            'permission' => 'dashboard.view',
        ],
        [
            'key' => 'administration',
            'heading' => 'admin.menu.administration',
            'permission' => ['users.view', 'roles.view', 'audit.view', 'settings.view'],
        ],
        [
            'key' => 'access',
            'label' => 'admin.menu.access_control',
            'icon' => 'ki-outline ki-shield-tick',
            'permission' => ['users.view', 'roles.view'],
            'children' => [
                [
                    'key' => 'users',
                    'label' => 'admin.menu.users',
                    'route' => 'admin.users.index',
                    'active' => ['admin.users.*'],
                    'permission' => 'users.view',
                ],
                [
                    'key' => 'roles',
                    'label' => 'admin.menu.roles',
                    'route' => 'admin.roles.index',
                    'active' => ['admin.roles.*'],
                    'permission' => 'roles.view',
                ],
            ],
        ],
        [
            'key' => 'audit',
            'label' => 'admin.menu.audit_logs',
            'icon' => 'ki-outline ki-time',
            'route' => 'admin.audit-logs.index',
            'active' => ['admin.audit-logs.*'],
            'permission' => 'audit.view',
        ],
        [
            'key' => 'settings',
            'label' => 'admin.menu.settings',
            'icon' => 'ki-outline ki-setting-2',
            'route' => 'admin.settings.edit',
            'active' => ['admin.settings.*'],
            'permission' => 'settings.view',
        ],
        [
            'key' => 'account',
            'heading' => 'admin.menu.account',
        ],
        [
            'key' => 'profile',
            'label' => 'admin.menu.profile',
            'icon' => 'ki-outline ki-user',
            'route' => 'admin.profile.edit',
            'active' => ['admin.profile.*'],
        ],
        [
            'key' => 'notifications',
            'label' => 'admin.menu.notifications',
            'icon' => 'ki-outline ki-notification-on',
            'route' => 'admin.notifications.index',
            'active' => ['admin.notifications.*'],
        ],
    ],

    // Upload limits (kilobytes) for branding assets.
    'uploads' => [
        'logo_max_kb' => 1024,
        'favicon_max_kb' => 256,
    ],
];
