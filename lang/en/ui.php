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
        'settings' => 'Settings',
        'dashboard' => 'Dashboard',
        'roles' => 'Roles',
        'positions' => 'Positions',
        'companies' => 'Companies',
        'premises' => 'Premises',
        'shifts' => 'Shifts',
        'employees' => 'Employees',
        'holidays' => 'Holidays',
        'leaves' => 'Leaves',
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

        'flash' => [
            'created' => 'Leave request created.',
            'approved' => 'Leave approved.',
            'rejected' => 'Leave rejected.',
            'deleted' => 'Leave deleted.',
        ],
    ],

];
