<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UI Language Lines
    |--------------------------------------------------------------------------
    |
    | User-visible strings for the React frontend. This file is the single
    | source of truth: it is exposed to the frontend via Inertia shared props
    | (see HandleInertiaRequests) and consumed with the t() helper. Add every
    | new string here and its counterpart in lang/es/ui.php.
    |
    */

    'nav' => [
        'organization' => 'Organization',
        'workdays' => 'Workdays',
        'documents' => 'Documents',
        'settings' => 'Settings',
        'dashboard' => 'Dashboard',
        'roles' => 'Roles',
        'positions' => 'Positions',
    ],

    'user_menu' => [
        'settings' => 'Settings',
        'logout' => 'Log out',
    ],

    'common' => [
        'save' => 'Save',
        'cancel' => 'Cancel',

        'data_table' => [
            'empty' => 'No results.',
            'toggle_columns' => 'Columns',
            'selected' => ':count of :total selected',
            'pagination' => [
                'showing' => 'Showing :from–:to of :total',
                'none' => 'No results',
                'previous' => 'Previous',
                'next' => 'Next',
            ],
        ],
    ],

    'dashboard' => [
        'title' => 'Dashboard',
    ],

    'settings' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account settings',

        'nav' => [
            'profile' => 'Profile',
            'security' => 'Security',
            'appearance' => 'Appearance',
        ],

        'profile' => [
            'head' => 'Profile settings',
            'title' => 'Profile',
            'description' => 'Update your name, email address, and avatar',
            'change_avatar' => 'Change avatar',
            'avatar_hint' => 'JPG, PNG or GIF. Max 2MB.',
            'name' => 'Name',
            'name_placeholder' => 'Full name',
            'email' => 'Email address',
            'email_placeholder' => 'Email address',
            'unverified' => 'Your email address is unverified.',
            'resend' => 'Click here to re-send the verification email.',
            'verification_sent' => 'A new verification link has been sent to your email address.',
        ],

        'security' => [
            'head' => 'Security settings',
            'title' => 'Update password',
            'description' => 'Ensure your account is using a long, random password to stay secure',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm password',
        ],

        'appearance' => [
            'head' => 'Appearance settings',
            'title' => 'Appearance settings',
            'description' => 'Update the appearance settings for your account',
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'System',
        ],

        'delete' => [
            'title' => 'Delete account',
            'description' => 'Delete your account and all of its resources',
            'warning' => 'Warning',
            'warning_body' => 'Please proceed with caution, this cannot be undone.',
            'button' => 'Delete account',
            'confirm_title' => 'Are you sure you want to delete your account?',
            'confirm_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
            'password' => 'Password',
        ],
    ],

    'language' => [
        'label' => 'Language',
        'es' => 'Spanish',
        'en' => 'English',
    ],

    'organizations' => [
        'nav' => 'Organizations',
        'title' => 'Organizations',
        'description' => 'Manage tenant organizations',
        'new' => 'New organization',
        'search_placeholder' => 'Search by name...',
        'empty' => 'No organizations found.',

        'columns' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'plan' => 'Plan',
            'users' => 'Users',
            'created' => 'Created',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'pagination' => [
            'showing' => 'Showing :from–:to of :total',
            'none' => 'No results',
            'previous' => 'Previous',
            'next' => 'Next',
        ],

        'form' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'plan' => 'Plan',
            'plan_placeholder' => 'Select a plan',
        ],

        'create' => [
            'title' => 'New organization',
            'description' => 'Onboard a new tenant organization',
            'submit' => 'Create organization',
        ],

        'edit' => [
            'title' => 'Edit organization',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete organization',
            'description' => 'Are you sure you want to delete :name? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Organization created.',
            'updated' => 'Organization updated.',
            'archived' => 'Organization archived.',
            'deleted' => 'Organization deleted.',
        ],
    ],

    'roles' => [
        'title' => 'Roles',
        'description' => 'Manage roles and their permissions',
        'search_placeholder' => 'Search by name...',
        'empty' => 'No roles found.',

        'columns' => [
            'role' => 'Role',
            'permissions' => 'Permissions',
        ],

        'actions' => [
            'manage' => 'Manage permissions',
        ],
    ],

    'positions' => [
        'title' => 'Positions',
        'description' => 'Job titles used to group employees',
        'new' => 'New position',
        'search_placeholder' => 'Search by name...',
        'empty' => 'No positions found.',
        'back' => 'Back to positions',

        'columns' => [
            'name' => 'Name',
            'employees' => 'Employees',
        ],

        'actions' => [
            'edit' => 'Rename',
            'delete' => 'Delete',
        ],

        'pagination' => [
            'showing' => 'Showing :from–:to of :total',
            'none' => 'No results',
            'previous' => 'Previous',
            'next' => 'Next',
        ],

        'form' => [
            'name' => 'Name',
            'name_placeholder' => 'e.g. Supervisor',
        ],

        'create_dialog' => [
            'title' => 'New position',
            'submit' => 'Create position',
        ],

        'edit_dialog' => [
            'title' => 'Rename position',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete position',
            'description' => 'Are you sure you want to delete :name? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'employees' => [
            'title' => 'Employees',
            'empty' => 'No employees assigned to this position.',
            'columns' => [
                'name' => 'Name',
                'email' => 'Email',
                'status' => 'Status',
            ],
            'status' => [
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ],

        'flash' => [
            'created' => 'Position created.',
            'updated' => 'Position updated.',
            'deleted' => 'Position deleted.',
            'has_employees' => 'This position cannot be deleted while employees are assigned to it.',
        ],
    ],

];
