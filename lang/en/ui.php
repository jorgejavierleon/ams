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
        'approvals' => 'Approvals',
        'documents' => 'Documents',
        'documents_list' => 'Documents',
        'document_templates' => 'Templates',
        'settings' => 'Settings',
        'dashboard' => 'Dashboard',
        'roles' => 'Roles',
        'positions' => 'Positions',
        'companies' => 'Companies',
        'premises' => 'Premises',
        'shifts' => 'Shifts',
        'workdays_list' => 'Workdays',
        'employees' => 'Employees',
        'holidays' => 'Holidays',
        'leaves' => 'Leaves',
        'leaves_calendar' => 'Leaves calendar',
        'my_leaves' => 'My leaves',
        'my_workdays' => 'My workdays',
        'my_documents' => 'My documents',
        'team_leaves' => 'Team leaves',
    ],

    'user_menu' => [
        'settings' => 'Settings',
        'logout' => 'Log out',
    ],

    'common' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'search' => 'Search...',
        'no_results' => 'No results found.',
        'yes' => 'Yes',
        'no' => 'No',

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

    'marks' => [
        'title' => 'Attendance mark',
        'subtitle' => 'Register when you enter and leave work.',
        'greeting' => 'Hi, :name',
        'no_shift' => 'You have no shift assigned for today.',
        'no_shift_chip' => 'No shift today',
        'shift_for_today' => "Today's shift: :start to :end",
        'check_in' => 'Check in',
        'check_out' => 'Check out',
        'complete_cta' => 'Workday complete',
        'worked' => 'Worked',
        'in_progress' => 'in progress',
        'current_time' => 'Current time',
        'in_marked' => 'Entry marked',
        'in_pending' => 'Entry pending',
        'out_marked' => 'Exit marked',
        'out_pending' => 'Exit pending',
        'marked_at' => 'Marked at :time',
        'types' => [
            'in' => 'Entry',
            'out' => 'Exit',
        ],
        'status' => [
            'idle' => "You haven't checked in yet",
            'working' => 'Working · :elapsed',
            'complete' => 'Workday recorded',
        ],
        'note' => [
            'idle' => 'The time is recorded automatically when you confirm.',
            'working' => 'Checking out closes your workday for today.',
            'complete' => 'You can mark your next entry tomorrow.',
        ],
        'confirm' => [
            'check_in_title' => 'Confirm check in',
            'check_out_title' => 'Confirm check out',
            'description' => 'Your mark will be registered with the current time. This action cannot be undone.',
            'action' => 'Confirm',
        ],
        'flash' => [
            'registered' => 'Mark registered successfully.',
            'already_marked' => 'You already registered this mark today.',
        ],
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

    'document_variables' => [
        'nav' => 'Document variables',
        'title' => 'Document variables',
        'description' => 'Global placeholders resolved when rendering documents',
        'new' => 'New variable',
        'search_placeholder' => 'Search by name or key...',
        'empty' => 'No document variables found.',

        'columns' => [
            'name' => 'Name',
            'key' => 'Key',
            'description' => 'Description',
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
            'key' => 'Key',
            'key_hint' => 'Use the {{snake_case}} format, e.g. {{employee_name}}.',
            'description' => 'Description',
        ],

        'validation' => [
            'key_format' => 'The key must follow the {{snake_case}} format, e.g. {{employee_name}}.',
        ],

        'create' => [
            'title' => 'New document variable',
            'description' => 'Define a new global placeholder',
            'submit' => 'Create variable',
        ],

        'edit' => [
            'title' => 'Edit document variable',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete document variable',
            'description' => 'Are you sure you want to delete :name? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Document variable created.',
            'updated' => 'Document variable updated.',
            'deleted' => 'Document variable deleted.',
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

    'holidays' => [
        'title' => 'Holidays',
        'description' => 'Official public holidays plus any your organization adds',
        'new' => 'New holiday',
        'search_placeholder' => 'Search by name...',
        'empty' => 'No holidays found.',
        'official' => 'Official',
        'custom' => 'Custom',

        'columns' => [
            'date' => 'Date',
            'name' => 'Name',
            'type' => 'Type',
            'mandatory' => 'Mandatory',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'yes' => 'Yes',
        'no' => 'No',

        'form' => [
            'name' => 'Name',
            'name_placeholder' => 'e.g. Independence Day',
            'date' => 'Date',
            'mandatory' => 'Mandatory',
            'mandatory_hint' => 'Mandatory holidays are always non-working days.',
        ],

        'create_dialog' => [
            'title' => 'New holiday',
            'submit' => 'Create holiday',
        ],

        'edit_dialog' => [
            'title' => 'Edit holiday',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete holiday',
            'description' => 'Are you sure you want to delete :name? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Holiday created.',
            'updated' => 'Holiday updated.',
            'deleted' => 'Holiday deleted.',
        ],
    ],

    'saas_holidays' => [
        'nav' => 'Holidays',
        'title' => 'Official holidays',
        'description' => 'The national holiday list shared with every organization',
        'empty' => 'No official holidays yet. Import a year to get started.',

        'columns' => [
            'date' => 'Date',
            'name' => 'Name',
            'mandatory' => 'Mandatory',
        ],

        'yes' => 'Yes',
        'no' => 'No',

        'import' => [
            'year' => 'Year',
            'submit' => 'Import from Boostr',
        ],

        'flash' => [
            'imported' => 'Imported :count holidays for :year.',
            'failed' => 'Could not fetch holidays from Boostr. Please try again.',
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

    'companies' => [
        'title' => 'Companies',
        'description' => 'Legal entities within your organization',
        'new' => 'New company',
        'search_placeholder' => 'Search by name or RUT...',
        'empty' => 'No companies found.',

        'columns' => [
            'name' => 'Company',
            'rut' => 'RUT',
            'region' => 'Region',
            'commune' => 'Commune',
            'employees' => 'Employees',
            'status' => 'Status',
        ],

        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'form' => [
            'details' => 'Company details',
            'social_reason' => 'Company name',
            'rut' => 'RUT',
            'rut_placeholder' => '12.345.678-9',
            'business_line' => 'Business line',
            'email' => 'Email',
            'region' => 'Region',
            'region_placeholder' => 'Select a region',
            'region_search' => 'Search region...',
            'region_empty' => 'No regions found.',
            'commune' => 'Commune',
            'commune_placeholder' => 'Select a commune',
            'commune_search' => 'Search commune...',
            'commune_empty' => 'No communes found.',
            'commune_loading' => 'Loading communes...',
            'commune_region_first' => 'Select a region first',
            'address' => 'Address',
            'address_hint' => 'Street name and number',
            'phone' => 'Phone',
            'company_type' => 'Company type',
            'is_est' => 'Temporary services company (EST)',
            'is_active' => 'Active',

            'representatives' => 'Legal representatives',
            'representatives_hint' => 'Each representative is created as a company user.',
            'add_representative' => 'Add representative',
            'no_representatives' => 'No representatives added yet.',
            'rep_rut' => 'RUT',
            'rep_first_name' => 'First name',
            'rep_last_name' => 'Last name',
            'rep_second_last_name' => 'Second last name',
            'rep_email' => 'Email',
            'remove' => 'Remove',
        ],

        'create' => [
            'title' => 'New company',
            'description' => 'Register a new company',
            'submit' => 'Create company',
        ],

        'edit' => [
            'title' => 'Edit company',
            'description' => 'Update company details and representatives',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete company',
            'description' => 'Are you sure you want to delete :name? It can be restored later.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Company created.',
            'updated' => 'Company updated.',
            'deleted' => 'Company deleted.',
        ],
    ],

    'premises' => [
        'title' => 'Premises',
        'description' => 'Physical work locations belonging to your companies',
        'new' => 'New premise',
        'search_placeholder' => 'Search by name, code or address...',
        'empty' => 'No premises found.',

        'columns' => [
            'name' => 'Name',
            'company' => 'Company',
            'address' => 'Address',
            'coordinates' => 'Location',
        ],

        'coordinates' => [
            'set' => 'Geolocated',
            'unset' => 'No coordinates',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'form' => [
            'details' => 'Premise details',
            'company' => 'Company',
            'company_placeholder' => 'Select a company',
            'company_search' => 'Search company...',
            'company_empty' => 'No companies found.',
            'name' => 'Name',
            'code' => 'Code',
            'address' => 'Address',
            'country' => 'Country',
            'region' => 'Region',
            'commune' => 'Commune',
            'location' => 'Location',
            'location_hint' => 'Click the map or drag the marker to set the coordinates.',
            'lat' => 'Latitude',
            'lng' => 'Longitude',
            'responsable' => 'Person in charge',
            'responsable_name' => 'Name',
            'responsable_email' => 'Email',
            'responsable_phone' => 'Phone',
        ],

        'map' => [
            'search' => 'Search',
            'search_placeholder' => 'Search an address...',
            'loading' => 'Loading map...',
            'not_found' => 'No results for that address.',
            'hint' => 'Click the map or drag the marker to place this premise.',
            'unavailable' => 'The map could not be loaded. Enter the coordinates manually below.',
        ],

        'create' => [
            'title' => 'New premise',
            'description' => 'Register a new work location',
            'submit' => 'Create premise',
        ],

        'edit' => [
            'title' => 'Edit premise',
            'description' => 'Update premise details and location',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete premise',
            'description' => 'Are you sure you want to delete :name? It can be restored later.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Premise created.',
            'updated' => 'Premise updated.',
            'deleted' => 'Premise deleted.',
            'has_employees' => 'This premise has active employees assigned and cannot be deleted.',
        ],
    ],

    'shifts' => [
        'title' => 'Shifts',
        'description' => 'Work schedule templates for your organization',
        'new' => 'New shift',
        'default' => 'Default',
        'search_placeholder' => 'Search by name...',
        'empty' => 'No shifts found.',

        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'weekly_hours' => 'Weekly hours',
            'assignments' => 'Assignments',
        ],

        'types' => [
            'fixed' => 'Fixed',
            'rotational' => 'Rotational',
            'cyclic' => 'Cyclic',
            'biweekly' => 'Biweekly',
            'exceptional' => 'Exceptional',
            'partial' => 'Partial',
        ],

        'weekdays' => [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'form' => [
            'details' => 'Shift details',
            'name' => 'Name',
            'type' => 'Type',
            'type_placeholder' => 'Select a type',
            'type_search' => 'Search type...',
            'type_empty' => 'No types found.',
            'description' => 'Description',
            'tolerance_in' => 'Entry tolerance (minutes)',
            'tolerance_out' => 'Exit tolerance (minutes)',
            'tolerance_placeholder' => 'e.g. 30',
            'tolerance_hint' => 'Grace period in minutes before a mark counts as late/early.',
            'is_default' => 'Default shift',
            'work_on_holidays' => 'Works on holidays',
            'is_archive' => 'Archived',
            'schedule' => 'Weekly schedule',
            'schedule_hint' => 'Set the working hours for each day. Uncheck a day to mark it non-working.',
        ],

        'schedule' => [
            'day' => 'Day',
            'working' => 'Working',
            'start' => 'Start',
            'end' => 'End',
            'lunch_start' => 'Lunch start',
            'lunch_end' => 'Lunch end',
            'hours' => 'Hours',
            'weekly_total' => 'Weekly total',
            'legal_max' => 'Legal maximum: :max hours per week.',
            'exceeds_weekly' => 'Exceeds the legal maximum of :max hours per week.',
            'exceeds_daily' => 'Exceeds the legal maximum of :max hours per day.',
        ],

        'validation' => [
            'exceeds_weekly' => 'The weekly total (:total h) exceeds the legal maximum of :max hours.',
            'negative_hours' => 'End time must be after start time (and lunch must fit within the day).',
            'incomplete_days' => 'Every working day needs a start, end and lunch time.',
        ],

        'create' => [
            'title' => 'New shift',
            'description' => 'Create a work schedule template',
            'submit' => 'Create shift',
        ],

        'edit' => [
            'title' => 'Edit shift',
            'description' => 'Update the shift and its weekly schedule',
            'submit' => 'Save changes',
        ],

        'delete_dialog' => [
            'title' => 'Delete shift',
            'description' => 'Are you sure you want to delete :name? It can be restored later.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Shift created.',
            'updated' => 'Shift updated.',
            'deleted' => 'Shift deleted.',
            'has_assignments' => 'This shift has active assignments and cannot be deleted.',
        ],

        'shift_assignments' => [
            'title' => 'Shift assignments',
            'add' => 'Add assignment',
            'empty' => 'No shift assignments yet.',
            'status_current' => 'Current',
            'status_ended' => 'Ended',
            'status_upcoming' => 'Upcoming',
            'permanent' => 'Permanent',

            'columns' => [
                'shift' => 'Shift',
                'start_date' => 'Start date',
                'end_date' => 'End date',
                'status' => 'Status',
            ],

            'actions' => [
                'end' => 'End',
                'delete' => 'Delete',
            ],

            'dialog' => [
                'title' => 'Add shift assignment',
                'description' => 'Assign a shift to this employee for a date range. Leave the end date empty for a permanent assignment.',
                'shift' => 'Shift',
                'shift_placeholder' => 'Select a shift',
                'shift_search' => 'Search shift...',
                'shift_empty' => 'No shifts found.',
                'start_date' => 'Start date',
                'end_date' => 'End date (optional)',
                'cancel' => 'Cancel',
                'submit' => 'Add assignment',
            ],

            'end_dialog' => [
                'title' => 'End assignment',
                'description' => 'This sets the end date to today. Continue?',
                'confirm' => 'End assignment',
            ],

            'delete_dialog' => [
                'title' => 'Delete assignment',
                'description' => 'Are you sure you want to delete this assignment? This action cannot be undone.',
                'confirm' => 'Delete',
            ],

            'validation' => [
                'overlap' => 'This date range overlaps an existing assignment for this employee.',
            ],

            'flash' => [
                'created' => 'Shift assignment created.',
                'ended' => 'Shift assignment ended.',
                'deleted' => 'Shift assignment deleted.',
            ],
        ],
    ],

    'employees' => [
        'title' => 'Employees',
        'description' => 'Manage the people in your organization',
        'new' => 'New employee',
        'search_placeholder' => 'Search by email or RUT...',
        'empty' => 'No employees found.',

        'vacation_balance' => [
            'title' => 'Vacation balance',
            'summary' => ':used / :total days used',
            'available' => ':available days available',
        ],

        'columns' => [
            'employee' => 'Employee',
            'email' => 'Email',
            'rut' => 'RUT',
            'position' => 'Position',
            'premise' => 'Premise',
            'is_admin' => 'Admin',
            'is_active' => 'Active',
            'admin_badge' => 'Admin',
        ],

        'filters' => [
            'active_all' => 'Active: all',
            'active_yes' => 'Active',
            'active_no' => 'Inactive',
            'admin_all' => 'Admin: all',
            'admin_yes' => 'Admins',
            'admin_no' => 'Non-admins',
            'premise' => 'Premise',
            'position' => 'Position',
            'clear' => 'Clear filters',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'tabs' => [
            'personal' => 'Personal',
            'labor' => 'Labor',
            'contact' => 'Contact',
            'system' => 'System',
        ],

        'form' => [
            'none' => 'None',
            'select' => 'Select an option',
            'search' => 'Search...',
            'no_results' => 'No results found.',
            'has_errors' => 'Please correct the errors below.',
            'avatar' => 'Avatar',
            'is_active' => 'Active',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'second_last_name' => 'Second last name',
            'rut' => 'RUT',
            'email' => 'Email',
            'password' => 'Password',
            'password_hint' => 'Leave blank to keep the current password.',
            'nationality' => 'Nationality',
            'gender' => 'Gender',
            'company' => 'Company',
            'premise' => 'Premise',
            'position' => 'Position',
            'supervisor' => 'Supervisor',
            'contract_start_date' => 'Contract start date',
            'contract_end_date' => 'Contract end date',
            'is_admin' => 'Administrator',
            'vacation_days' => 'Vacation days',
            'additional_vacation_days' => 'Additional vacation days',
            'administrative_days' => 'Administrative days',
            'has_additional_sundays' => 'Has additional Sundays',
            'personal_email' => 'Personal email',
            'phone' => 'Phone',
            'emergency_contact_name' => 'Emergency contact name',
            'emergency_contact_phone' => 'Emergency contact phone',
            'timezone' => 'Timezone',
            'cancel' => 'Cancel',
        ],

        'create' => [
            'title' => 'New employee',
            'description' => 'Add a new person to your organization',
            'submit' => 'Create employee',
        ],

        'edit' => [
            'title' => 'Edit employee',
            'description' => 'Update the employee details',
            'submit' => 'Save changes',
        ],

        'show' => [
            'tab_info' => 'Info',
            'tab_labor' => 'Labor',
            'tab_shifts' => 'Shifts',
            'tab_documents' => 'Documents',
            'yes' => 'Yes',
            'no' => 'No',
            'shifts_pending' => 'Shift assignments will be available soon.',
            'documents_pending' => 'Documents will be available soon.',
        ],

        'delete_dialog' => [
            'title' => 'Delete employee',
            'description' => 'Are you sure you want to delete :name? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'flash' => [
            'created' => 'Employee created.',
            'updated' => 'Employee updated.',
            'deleted' => 'Employee deleted.',
        ],
    ],

    'leaves' => [
        'title' => 'Leaves',
        'description' => 'Manage employee time-off requests',
        'new' => 'New leave',
        'empty' => 'No leave requests found.',

        'calendar' => [
            'title' => 'Leaves calendar',
            'description' => 'Approved time off across the organization',
            'legend' => 'Leave types',
            'employee' => 'Employee',
            'type' => 'Type',
            'dates' => 'Dates',
            'approved_by' => 'Approved by',
            'none' => '—',
        ],

        'tabs' => [
            'all' => 'All',
        ],

        'columns' => [
            'employee' => 'Employee',
            'type' => 'Type',
            'start_date' => 'Start',
            'end_date' => 'End',
            'half_day' => 'Half day',
            'days' => 'Days',
            'status' => 'Status',
            'approved_by' => 'Approved by',
        ],

        'filters' => [
            'employee' => 'Employee',
            'from' => 'From',
            'to' => 'To',
        ],

        'actions' => [
            'view' => 'View details',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'delete' => 'Delete',
            'cancel' => 'Cancel request',
            'more' => 'More actions',
        ],

        'statuses' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ],

        'detail' => [
            'title' => 'Leave details',
            'employee' => 'Employee',
            'type' => 'Type',
            'status' => 'Status',
            'start_date' => 'Start date',
            'end_date' => 'End date',
            'half_day' => 'Half day',
            'days' => 'Business days',
            'approved_by' => 'Approved by',
            'created_at' => 'Requested at',
            'medical' => 'Medical leave',
            'medical_leave_number' => 'Leave number',
            'medical_leave_doctor' => 'Doctor',
            'notes' => 'Notes',
            'no_notes' => 'No notes provided.',
            'none' => '—',
        ],

        'types' => [
            'vacation_lead' => 'Vacation',
            'medical_lead' => 'Medical',
            'unpaid_lead' => 'Unpaid',
            'paid_lead' => 'Paid',
            'other_lead' => 'Other',
        ],

        'half_day_types' => [
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
        ],

        'create' => [
            'title' => 'New leave',
            'description' => 'Register a time-off request for an employee',
            'submit' => 'Create leave',
        ],

        'form' => [
            'employee' => 'Employee',
            'employee_placeholder' => 'Select an employee',
            'employee_search' => 'Search employees...',
            'employee_empty' => 'No employees found.',
            'type' => 'Leave type',
            'type_placeholder' => 'Select a type',
            'type_search' => 'Search types...',
            'type_empty' => 'No types found.',
            'start_date' => 'Start date',
            'end_date' => 'End date',
            'half_day' => 'Half day',
            'half_day_type' => 'Half-day period',
            'half_day_type_placeholder' => 'Select a period',
            'business_days' => 'Business days requested',
            'business_days_hint' => 'Estimated from the shift and holidays — adjust if needed.',
            'business_days_half_hint' => 'Half-day leaves always count as 0.5 days.',
            'medical_leave_number' => 'Medical leave number',
            'medical_leave_doctor' => 'Doctor',
            'notes' => 'Notes',
        ],

        'validation' => [
            'half_day_single_day' => 'A half-day leave must start and end on the same day.',
        ],

        'approve_dialog' => [
            'title' => 'Approve leave',
            'description' => "Approve :name's leave request? For vacation, the days will be deducted from their balance.",
        ],

        'reject_dialog' => [
            'title' => 'Reject leave',
            'description' => "Reject :name's leave request?",
        ],

        'delete_dialog' => [
            'title' => 'Delete leave',
            'description' => "Delete :name's leave request? This cannot be undone. For an approved vacation, the days will be returned to their balance.",
        ],

        'my' => [
            'title' => 'My leaves',
            'description' => 'Request time off and track your requests',
            'new' => 'Request leave',
            'empty' => 'You have no leave requests yet.',

            'create' => [
                'title' => 'Request leave',
                'description' => 'Submit a time-off request for approval',
                'submit' => 'Submit request',
            ],

            'cancel_dialog' => [
                'title' => 'Cancel request',
                'description' => 'Cancel this pending leave request? This cannot be undone.',
            ],
        ],

        'flash' => [
            'created' => 'Leave request created.',
            'approved' => 'Leave approved.',
            'rejected' => 'Leave rejected.',
            'deleted' => 'Leave deleted.',
        ],
    ],

    'workdays' => [
        'title' => 'Workdays',
        'description' => 'Daily attendance for every employee',
        'empty' => 'No workdays found for this range.',
        'select_all' => 'Select all rows',
        'select_row' => 'Select row',
        'selected' => ':count selected',
        'pending_hint' => 'Pending mark modification requests',

        'ranges' => [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'week' => 'This week',
            'month' => 'This month',
        ],

        'columns' => [
            'employee' => 'Employee',
            'date' => 'Date',
            'status' => 'Status',
            'mark_in' => 'In',
            'mark_out' => 'Out',
            'worked' => 'Worked',
            'shift_delta' => 'Delta (in / out)',
            'shift' => 'Shift',
            'leave' => 'Leave',
        ],

        'filters' => [
            'status' => 'Status',
            'employee' => 'Employee',
            'position' => 'Position',
            'premise' => 'Premise',
            'from' => 'From',
            'to' => 'To',
        ],

        'statuses' => [
            'regular' => 'Regular',
            'irregular' => 'Irregular',
            'absent' => 'Absent',
            'incomplete' => 'Incomplete',
            'justified' => 'Justified',
        ],

        'actions' => [
            'view' => 'View details',
            'modify' => 'Modify marks',
        ],

        'modify' => [
            'title' => 'Modify marks',
            'description' => 'Adjust the times below for :employee on :date. Only the marks you change are sent for review; the employee is notified.',
            'mark_in' => 'Entry mark',
            'mark_out' => 'Exit mark',
            'no_mark' => 'No mark registered yet',
            'reason' => 'Reason',
            'notes' => 'Notes',
            'submit' => 'Submit request',
        ],

        'detail' => [
            'title' => 'Workday details',
            'in_delta' => 'Entry delta',
            'out_delta' => 'Exit delta',
            'pending' => 'Pending modifications',
        ],

        'show' => [
            'back' => 'Back to workdays',
            'eyebrow' => 'Workday',
            'scheduled' => 'Scheduled',
            'edit_locked' => 'Editing locked',
            'attendance_title' => 'Attendance',
            'shift_range' => 'Shift :range',
            'extra_sub' => 'over the shift',
            'missing_sub' => 'of the shift',
            'requested_by' => 'Requested by',
            'reviewed_inline' => 'reviewed by',
            'requests_count' => ':count request(s)',
            'strip' => [
                'entry' => 'Shift start',
                'exit' => 'Shift end',
                'legend_shift' => 'Scheduled shift',
                'legend_late' => 'Late entry',
                'legend_extra' => 'Exit with overtime',
            ],
            'delta' => [
                'late' => 'late',
                'early' => 'early',
                'extra' => 'extra',
                'on_time' => 'On time',
            ],
            'employee' => 'Employee',
            'no_shift' => 'No shift assigned',
            'no_premise' => 'No premise',
            'no_leave' => 'No leave',
            'leave_range' => ':type (:start - :end)',
            'mark_in' => 'Entry mark',
            'mark_out' => 'Exit mark',
            'no_mark' => 'No mark',
            'pending_badge' => 'Pending modification',
            'modified_badge' => 'Modified',
            'view_mark' => 'View mark',
            'modify_mark' => 'Modify mark',
            'summary_title' => 'Summary',
            'worked' => 'Worked time',
            'extra' => 'Extra time',
            'missing' => 'Missing time',
            'mark_details' => [
                'title' => 'Mark details',
                'date' => 'Record date',
                'time' => 'Record time',
                'type' => 'Mark type',
                'shift' => 'Shift',
                'employee_name' => 'Employee name',
                'employee_rut' => 'Employee RUT',
                'employer_name' => 'Employer name',
                'employer_rut' => 'Employer RUT',
                'premise_name' => 'Premise',
                'premise_address' => 'Premise address',
                'coordinates' => 'Coordinates',
            ],
            'history' => [
                'title' => 'Mark modifications',
                'empty' => 'No mark modifications',
                'type' => 'Mark',
                'status' => 'Status',
                'original' => 'Original',
                'modified' => 'Modified',
                'approve' => 'Approve',
                'decline' => 'Decline',
                'view_detail' => 'View detail',
                'confirm_approve' => 'Approve this mark modification?',
                'confirm_decline' => 'Decline this mark modification?',
            ],
            'detail' => [
                'title' => 'Modification details',
                'reason' => 'Reason',
                'notes' => 'Comment',
                'created_by' => 'Created by',
                'created_at' => 'Created at',
                'reviewed_by' => 'Reviewed by',
                'reviewed_at' => 'Reviewed at',
                'not_reviewed' => 'Not reviewed',
            ],
            'flash' => [
                'approved' => 'Modification approved.',
                'declined' => 'Modification declined.',
            ],
        ],

        'bulk' => [
            'trigger' => 'Modify marks',
            'title' => 'Modify marks',
            'description' => 'Open a mark modification request for :count selected workday(s).',
            'mark_type' => 'Mark',
            'time' => 'New time',
            'reason' => 'Reason',
            'notes' => 'Notes',
            'submit' => 'Submit requests',
        ],

        'flash' => [
            'bulk_modified' => ':count mark modification request(s) created.',
            'modified' => ':count mark modification request(s) created.',
            'modify_blocked' => 'No requests were created — the changed marks already have pending requests.',
            'no_changes' => 'No changes detected — no modification was requested.',
            'too_soon' => 'A correction can only be made from the business day after the day being corrected.',
        ],

        'my' => [
            'title' => 'My workdays',
            'description' => 'Review your attendance and respond to requested corrections.',
            'empty' => 'You have no workdays in this range.',
            'back' => 'Back to my workdays',

            'pending' => [
                'title' => 'Corrections to review',
                'subtitle' => 'An admin requested these mark adjustments. Approve or decline them.',
                'count' => ':count to review',
                'requested_by' => 'Requested by :name',
                'original' => 'Current mark',
                'proposed' => 'Proposed mark',
                'no_mark' => 'No mark',
                'reason' => 'Reason',
                'notes' => 'Comment',
                'approve' => 'Approve',
                'decline' => 'Decline',
                'expired' => 'Expired',
                'expired_hint' => 'The window to review this correction has passed.',
            ],

            'list' => [
                'title' => 'Workday history',
                'pending_flag' => 'Pending correction',
            ],

            'columns' => [
                'date' => 'Date',
                'status' => 'Status',
                'mark_in' => 'In',
                'mark_out' => 'Out',
                'worked' => 'Worked',
                'shift' => 'Shift',
            ],

            'filters' => [
                'from' => 'From',
                'to' => 'To',
            ],

            'flash' => [
                'approved' => 'Correction approved. Your mark was updated.',
                'declined' => 'Correction declined. Your mark is unchanged.',
            ],
        ],
    ],

    'mark_modifications' => [
        'statuses' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'declined' => 'Declined',
        ],

        'reasons' => [
            'mark_forgotten' => 'Forgotten mark',
            'mark_incorrect' => 'Incorrect mark',
            'system_error' => 'System error',
            'shift_change' => 'Shift change',
            'justified_missing_time' => 'Justified missing time',
            'inside_tolerance_time' => 'Within tolerance',
            'other' => 'Other',
        ],

        'review' => [
            'title' => 'Review mark correction',
            'description' => 'Approve or decline the requested change to your attendance mark.',
            'employee' => 'Employee',
            'mark_type' => 'Mark',
            'original' => 'Original time',
            'proposed' => 'Proposed time',
            'no_mark' => 'No mark on record',
            'reason' => 'Reason',
            'notes' => 'Notes',
            'approve' => 'Approve',
            'decline' => 'Decline',
            'approved_title' => 'Correction approved',
            'approved_body' => 'Your attendance mark has been updated. You can close this page.',
            'declined_title' => 'Correction declined',
            'declined_body' => 'The request was declined and your mark stays unchanged. You can close this page.',
            'expired_title' => 'Objection window closed',
            'expired_body' => '48 hours passed without objection, so the correction was applied automatically. It can no longer be approved or declined.',
        ],
    ],

    'dt' => [
        'nav' => [
            'dashboard' => 'Dashboard',
            'validate_mark' => 'Validate mark',
            'incidents' => 'Incidents',
            'documents' => 'Documents',
            'select_organization' => 'Change employer',
        ],
        'organization' => [
            'select' => [
                'title' => 'Select an employer to audit',
                'description' => 'Choose the employer whose records you want to review. Every view in this session will be scoped to your selection.',
                'search_placeholder' => 'Search by name or RUT',
                'columns' => [
                    'name' => 'Employer',
                    'rut' => 'RUT',
                ],
                'current' => 'Currently auditing',
                'submit' => 'Audit this employer',
                'empty' => 'There are no employers available to audit.',
                'no_results' => 'No employers match your search.',
            ],
        ],
        'marks' => [
            'validate' => [
                'title' => 'Validate mark',
                'description' => 'Paste the SHA-256 checksum printed on an attendance proof to verify its integrity against the database.',
                'checksum' => 'Checksum or hash',
                'checksum_placeholder' => 'Paste the mark checksum here',
                'submit' => 'Validate',
                'not_found' => 'No mark was found with that checksum.',
                'result_title' => 'Mark information',
                'result_description' => 'The checksum matches the following mark information.',
                'employee_name' => 'Employee name',
                'employee_rut' => 'Employee RUT',
                'employer_name' => 'Employer name',
                'employer_rut' => 'Employer RUT',
                'date_time' => 'Registration date and time',
                'type' => 'Mark type',
                'premise_name' => 'Premise',
                'premise_address' => 'Premise address',
                'coordinates' => 'Coordinates',
                'checksum_value' => 'Checksum',
                'not_available' => 'Not available',
            ],
        ],
        'incidents' => [
            'title' => 'Technical incidents',
            'description' => 'Outages of the electronic attendance system recorded for the audited employer.',
            'columns' => [
                'start_time' => 'Start',
                'end_time' => 'End',
                'duration' => 'Duration',
                'description' => 'Description',
            ],
            'filters' => [
                'from' => 'From date',
                'to' => 'To date',
            ],
            'ongoing' => 'Ongoing',
            'empty' => 'No incidents were recorded for this employer.',
        ],
        'documents' => [
            'title' => 'Documents',
            'description' => 'Employment documents recorded for the audited employer.',
            'columns' => [
                'employee' => 'Employee',
                'type' => 'Type',
                'status' => 'Status',
                'published_at' => 'Published',
                'signed_at' => 'Signed',
            ],
            'empty' => 'No documents were recorded for this employer.',
            'show' => [
                'back' => 'Back to documents',
                'details' => 'Details',
                'body' => 'Content',
                'body_empty' => 'This document has no content yet.',
                'download' => 'Download PDF',
            ],
        ],
    ],

    'documents' => [
        'title' => 'Documents',
        'description' => 'Draft, publish and track employee documents',
        'new' => 'New document',
        'search_placeholder' => 'Search by title...',
        'empty' => 'No documents found.',

        'columns' => [
            'title' => 'Title',
            'type' => 'Type',
            'employee' => 'Employee',
            'status' => 'Status',
            'published_at' => 'Published',
            'signed_at' => 'Signed',
        ],

        'filters' => [
            'status_all' => 'Status: all',
            'type_all' => 'Type: all',
            'employee' => 'Employee',
            'from' => 'Published from',
            'to' => 'Published to',
            'clear' => 'Clear filters',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
            'publish' => 'Publish',
            'download' => 'Download PDF',
            'void' => 'Void document',
            'duplicate' => 'Duplicate as draft',
        ],

        'statuses' => [
            'draft' => 'Draft',
            'published' => 'Published',
            'pending_signature' => 'Pending signature',
            'signed' => 'Signed',
            'rejected' => 'Rejected',
            'voided' => 'Voided',
            'archived' => 'Archived',
        ],

        'types' => [
            'annexes' => 'Annex',
            'contracts' => 'Contract',
            'certificates' => 'Certificate',
            'regulations' => 'Regulation',
            'pacts' => 'Pact',
            'notifications' => 'Notification',
            'requests' => 'Request',
            'others' => 'Other',
        ],

        'create' => [
            'title' => 'New document',
            'description' => 'Draft a document for an employee',
            'submit' => 'Create document',
        ],

        'edit' => [
            'title' => 'Edit document',
            'description' => 'Update the document details',
            'submit' => 'Save changes',
        ],

        'form' => [
            'title' => 'Title',
            'type' => 'Document type',
            'type_placeholder' => 'Select a type',
            'employee' => 'Employee',
            'employee_placeholder' => 'Select an employee',
            'body' => 'Body',
            'body_hint' => 'Use "Insert variable" to drop in placeholders resolved on publish.',
            'body_placeholder' => 'Write the document…',
            'signature_config' => 'Signature configuration',
            'legal_rep_signatories' => 'Legal rep signatories',
            'legal_rep_signatories_hint' => 'How many legal representatives must sign.',
            'ordered_signing' => 'Ordered signing',
            'ordered_signing_hint' => 'Require the legal representatives to sign in order.',
            'load_template' => 'Load template',
            'load_template_hint' => 'Pre-fill the body from a saved template.',
            'template_search' => 'Search templates...',
            'template_empty' => 'No templates found.',
        ],

        'editor' => [
            'bold' => 'Bold',
            'italic' => 'Italic',
            'heading' => 'Heading',
            'bullet_list' => 'Bullet list',
            'ordered_list' => 'Numbered list',
            'quote' => 'Quote',
            'undo' => 'Undo',
            'redo' => 'Redo',
            'insert_variable' => 'Insert variable',
            'variable_search' => 'Search variables...',
            'variable_empty' => 'No variables found.',
        ],

        'show' => [
            'back' => 'Back to documents',
            'eyebrow' => 'Document',
            'body' => 'Document body',
            'body_hint' => 'Preview with variables resolved for this employee.',
            'body_empty' => 'This document has no body yet.',
            'details' => 'Details',
            'employee' => 'Employee',
            'legal_rep_signatories' => 'Legal rep signatories',
            'ordered_signing' => 'Ordered signing',
            'signatures' => 'Signatures',
            'activity' => 'Activity',
        ],

        'activity' => [
            'empty' => 'No activity recorded',
            'status_change' => ':from → :to',
            'events' => [
                'published' => [
                    'title' => 'Document published',
                    'description' => 'The document was published successfully.',
                ],
                'signature_requested' => [
                    'title' => 'Signature requested',
                    'description' => 'A signature was requested from :name.',
                ],
                'signature_signed' => [
                    'title' => 'Signature recorded',
                    'description' => ':name signed the document.',
                ],
                'signed' => [
                    'title' => 'Document signed',
                    'description' => 'The document was signed by all parties.',
                ],
                'signature_rejected' => [
                    'title' => 'Signature rejected',
                    'description' => ':name rejected the document signature.',
                ],
                'voided' => [
                    'title' => 'Document voided',
                    'description' => 'The document was voided and can no longer be signed.',
                ],
            ],
        ],

        'pdf' => [
            'signatures_heading' => 'Simple electronic signatures',
            'rut' => 'RUT',
            'email' => 'Email',
            'signed_at' => 'Date and time',
            'hash' => 'Verification code',
        ],

        'signatures' => [
            'statuses' => [
                'pending' => 'Pending',
                'signed' => 'Signed',
                'rejected' => 'Rejected',
                'cancelled' => 'Cancelled',
            ],
            'types' => [
                'employee' => 'Employee',
                'legal_rep' => 'Legal representative',
                'supervisor' => 'Supervisor',
            ],
            'empty' => 'This document has no signatures yet. They are created when it is published.',
            'progress' => 'signed',
            'signed_at' => 'Signed on :date',
            'resend' => [
                'action' => 'Resend',
                'sent' => 'Signature request resent.',
                'not_pending' => 'Only pending signatures can be resent.',
            ],
            'sign' => [
                'code_sent' => 'We sent a verification code to your personal email.',
                'not_your_turn' => 'It is not your turn to sign this document yet.',
                'invalid_code' => 'The code is invalid or has expired.',
                'signed' => 'Document signed successfully.',
                'rejected' => 'You have rejected the document.',
            ],
        ],

        'my' => [
            'title' => 'My documents',
            'description' => 'Documents published to you and their signature status.',
            'empty' => 'You have no published documents.',
            'awaiting_you' => 'Awaiting your signature',
            'view' => 'View',
            'columns' => [
                'title' => 'Document',
                'type' => 'Type',
                'status' => 'Status',
                'my_signature' => 'My signature',
                'published_at' => 'Published',
            ],
            'show' => [
                'back' => 'Back to my documents',
                'eyebrow' => 'Document',
                'body' => 'Document content',
                'download_signed' => 'Download signed copy',
                'sign_panel' => 'Electronic signature',
                'request_code' => 'Request code',
                'resend_code' => 'Resend code',
                'code_label' => 'Verification code',
                'code_hint' => 'Enter the 6-digit code we sent to your personal email.',
                'sign' => 'Sign document',
                'reject' => 'Reject',
                'reject_reason' => 'Rejection reason (optional)',
                'reject_confirm_title' => 'Reject document',
                'reject_confirm_description' => 'Rejecting means the document can no longer be signed by any party. Continue?',
                'already_signed' => 'You have already signed this document.',
                'already_rejected' => 'You rejected this document.',
                'waiting_others' => 'Waiting for the other parties to sign.',
                'not_your_turn' => 'You will be able to sign when it is your turn.',
            ],
        ],

        'flash' => [
            'created' => 'Document created.',
            'updated' => 'Document updated.',
            'deleted' => 'Document deleted.',
            'published' => 'Document published.',
            'voided' => 'Document voided.',
            'duplicated' => 'Draft copy created. Make your corrections and publish.',
        ],

        'duplicate' => [
            'title_suffix' => ':title (copy)',
        ],

        'delete_dialog' => [
            'title' => 'Delete document',
            'description' => 'Are you sure you want to delete ":title"? This action cannot be undone.',
            'confirm' => 'Delete',
        ],

        'publish_dialog' => [
            'title' => 'Publish document',
            'description' => 'Publishing resolves the document variables and stamps the publish date. Continue?',
            'confirm' => 'Publish',
        ],

        'void_dialog' => [
            'title' => 'Void document',
            'description' => 'Voiding withdraws the document and cancels any pending signatures — it can no longer be signed. The document stays in the record for audit. To correct it, duplicate it as a draft afterwards. Continue?',
            'confirm' => 'Void document',
        ],
    ],

    'document_templates' => [
        'title' => 'Document templates',
        'description' => 'Reusable document bodies you can load into new documents',
        'new' => 'New template',
        'search_placeholder' => 'Search by title...',
        'empty' => 'No templates found.',

        'columns' => [
            'title' => 'Title',
            'type' => 'Type',
            'variables' => 'Variables',
            'updated_at' => 'Updated',
            'state' => 'State',
        ],

        'state' => [
            'active' => 'Active',
            'deleted' => 'Deleted',
        ],

        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
            'restore' => 'Restore',
        ],

        'create' => [
            'title' => 'New template',
            'description' => 'Draft a reusable document template',
            'submit' => 'Create template',
        ],

        'edit' => [
            'title' => 'Edit template',
            'description' => 'Update the template details',
            'submit' => 'Save changes',
        ],

        'form' => [
            'title' => 'Title',
            'type' => 'Document type',
            'type_placeholder' => 'Select a type',
            'body' => 'Body',
            'body_hint' => 'Click a variable to insert its placeholder at the caret.',
            'body_placeholder' => 'Write the template…',
        ],

        'flash' => [
            'created' => 'Template created.',
            'updated' => 'Template updated.',
            'deleted' => 'Template deleted.',
            'restored' => 'Template restored.',
        ],

        'delete_dialog' => [
            'title' => 'Delete template',
            'description' => 'Are you sure you want to delete ":title"? You can restore it later.',
            'confirm' => 'Delete',
        ],
    ],

];
