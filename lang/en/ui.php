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
    ],

    'user_menu' => [
        'settings' => 'Settings',
        'logout' => 'Log out',
    ],

    'common' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
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

];
