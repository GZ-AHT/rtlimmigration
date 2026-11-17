<?php

/*
|--------------------------------------------------------------------------
| Role & permission registry (REQ §6, §25, §27)
|--------------------------------------------------------------------------
|
| Permissions are named "<module>.<action>". This file is the single list
| the seeder syncs into the database. Super Admin bypasses all checks via
| Gate::before, so it is not given explicit permissions here.
|
| Record-level scoping (e.g. counselors only see their assigned students)
| is enforced by Policies, not by these permission names.
|
*/

return [

    'permissions' => [
        'Areas' => [
            'area.admin' => 'Enter the admin panel',
            'area.counselor' => 'Enter the counselor dashboard',
            'area.accounts' => 'Enter the accounts dashboard',
            'area.portal' => 'Enter the student portal',
        ],
        'Dashboard' => [
            'dashboard.view' => 'View the admin dashboard overview',
        ],
        'Leads' => [
            'leads.view' => 'View leads',
            'leads.create' => 'Create leads',
            'leads.update' => 'Update leads, notes and follow-ups',
            'leads.assign' => 'Assign leads to counselors',
            'leads.transfer' => 'Transfer leads between counselors',
            'leads.convert' => 'Convert a lead to a student',
            'leads.delete' => 'Delete leads',
        ],
        'Users' => [
            'users.view' => 'View users',
            'users.manage' => 'Create, edit, deactivate and delete users',
        ],
        'Students' => [
            'students.view' => 'View students',
            'students.manage' => 'Create and edit students',
            'students.assign-counselor' => 'Assign counselors to students',
        ],
        'Counselors' => [
            'counselors.view' => 'View counselors',
            'counselors.manage' => 'Create and edit counselors',
        ],
        'Student files' => [
            'files.view' => 'View student files',
            'files.manage' => 'Open and update student files',
        ],
        'Documents' => [
            'documents.view' => 'View student documents',
            'documents.manage' => 'Upload and replace student documents',
            'documents.verify' => 'Verify or reject student documents',
        ],
        'Payments' => [
            'payments.view' => 'View payments and payment requests',
            'payments.request' => 'Generate payment requests',
            'payments.verify' => 'Verify submitted payments',
            'payments.confirm' => 'Confirm or reject payments',
            'payment-types.manage' => 'Manage payment types and methods',
        ],
        'Invoices & refunds' => [
            'invoices.view' => 'View invoices and receipts',
            'refunds.request' => 'Request refunds',
            'refunds.approve' => 'Approve refunds',
            'refunds.process' => 'Process approved refunds',
            'expenses.manage' => 'Record expenses',
        ],
        'Visa processing' => [
            'applications.view' => 'View applications and visa progress',
            'applications.update' => 'Update application stages',
            'applications.decide' => 'Record visa decisions',
            'workflows.manage' => 'Configure application workflows and stages',
        ],
        'Universities' => [
            'universities.view' => 'View universities',
            'universities.manage' => 'Manage universities, courses and intakes',
        ],
        'Partners' => [
            'partners.view' => 'View partner applications and partners',
            'partners.manage' => 'Manage partners',
            'partners.approve' => 'Approve or reject partner applications',
        ],
        'Working visa' => [
            'working-visa.view' => 'View working visa applications',
            'working-visa.manage' => 'Manage visa categories and requirements',
        ],
        'Exams & work' => [
            'exams.view' => 'View exams and work items',
            'exams.manage' => 'Manage exams, schedules and results',
        ],
        'Email' => [
            'email.access' => 'Use assigned webmail',
            'email.manage' => 'Manage email accounts, templates and SMTP',
        ],
        'Live chat' => [
            'chat.access' => 'Use live chat (own and own students\' chats)',
            'chat.view-all' => 'See and assign every chat and offline message',
            'chat.manage' => 'Manage live chat settings and keys',
            'chat.moderate' => 'Review flagged chat messages and approve or reject them',
        ],
        'Tickets & contacts' => [
            'tickets.view' => 'View tickets and contact inquiries',
            'tickets.manage' => 'Reply to and update tickets',
            'tickets.assign' => 'Assign tickets',
        ],
        'Blog' => [
            'blog.view' => 'View blog posts',
            'blog.manage' => 'Create and edit blog posts',
            'blog.publish' => 'Publish and schedule blog posts',
        ],
        'Pages' => [
            'pages.view' => 'View pages',
            'pages.manage' => 'Create and edit pages',
            'pages.publish' => 'Publish and schedule pages',
        ],
        'Website CMS' => [
            'cms.manage' => 'Manage website settings, menus, header, footer and homepage',
            'cms.custom-code' => 'Edit custom CSS and JavaScript',
        ],
        'SEO' => [
            'seo.manage' => 'Manage SEO, sitemap, robots.txt and redirects',
        ],
        'Reports' => [
            'reports.view' => 'View reports',
            'reports.financial' => 'View financial reports',
            'reports.export' => 'Export reports',
        ],
        'Notifications' => [
            'notifications.manage' => 'Manage notification templates and settings',
        ],
        'Media' => [
            'media.view' => 'Browse the media library',
            'media.manage' => 'Upload and delete media',
        ],
        'Integrations' => [
            'integrations.manage' => 'Manage third-party integrations',
        ],
        'Roles & permissions' => [
            'roles.manage' => 'Manage roles and permissions',
        ],
        'System' => [
            'settings.manage' => 'Manage system settings',
            'system.maintenance' => 'Clear caches and toggle maintenance mode',
            'backups.manage' => 'Create and download backups',
        ],
        'Security & audit' => [
            'audit.view' => 'View activity, login and audit logs',
            'security.manage' => 'Manage security settings',
        ],
    ],

    /*
    | Default permissions per built-in role. Super Admin is omitted (it
    | passes every check). Changes made in the admin UI are kept: the seeder
    | only adds missing defaults, it never strips permissions an admin added.
    */
    'roles' => [
        'lead-manager' => [
            'area.admin', 'dashboard.view',
            'leads.view', 'leads.create', 'leads.update', 'leads.assign', 'leads.transfer', 'leads.convert',
            'counselors.view',
            'tickets.view', 'tickets.manage', 'tickets.assign',
            'email.access', 'chat.access', 'chat.view-all',
            'reports.view',
        ],
        'counselor' => [
            'area.counselor',
            'leads.view', 'leads.update', 'leads.convert',
            'students.view', 'students.manage',
            'files.view', 'files.manage',
            'documents.view', 'documents.manage', 'documents.verify',
            'payments.view', 'payments.request',
            'invoices.view', 'refunds.request',
            'applications.view', 'applications.update', 'applications.decide',
            'exams.view', 'exams.manage',
            'email.access', 'chat.access',
            'reports.view',
        ],
        'accounts' => [
            'area.accounts',
            'students.view',
            'payments.view', 'payments.verify', 'payments.confirm',
            'invoices.view', 'refunds.process', 'expenses.manage',
            // REQ §9.1 lists "Payment Methods" among the Accounts menus.
            'payment-types.manage',
            'email.access',
            'reports.view', 'reports.financial', 'reports.export',
        ],
        'student' => [
            'area.portal',
        ],
    ],

];
