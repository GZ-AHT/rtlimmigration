<?php

/*
|--------------------------------------------------------------------------
| Dashboard navigation registry (REQ §8.1, §9.1, §10.1, §25)
|--------------------------------------------------------------------------
|
| One menu per area. Each item:
|   title       label shown in the sidebar
|   icon        Material Symbols name (matches admin-demo.html)
|   route       named route; null while the module is not built yet
|   permission  permission required to see the item (null = area access only)
|   phase       roadmap phase that delivers the module (docs/ROADMAP.md)
|
|   params      optional route parameters
|
| An item may instead have `children` (title, route, permission, phase),
| rendered as an expandable sub-menu.
|
| Items are filtered server-side by permission (App\Support\Navigation).
| Items whose route does not exist yet are hidden unless show_planned is on,
| in which case they render disabled with their phase number. That lets
| reviewers see the full structure during development.
|
*/

// Tawk.to screens appear only while the integration is on (config/chat.php).
$tawk = (bool) env('TAWK_ENABLED', false);

return [

    'show_planned' => env('NAV_SHOW_PLANNED', env('APP_ENV') === 'local'),

    'areas' => [

        // REQ §25: the 26 admin modules, in the recommended order.
        'admin' => [
            ['label' => 'Overview', 'items' => [
                ['title' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view', 'phase' => 1],
            ]],
            ['label' => 'People & files', 'items' => [
                ['title' => 'Lead & User Management', 'icon' => 'person_search', 'children' => [
                    ['title' => 'Users', 'route' => 'admin.users.index', 'permission' => 'users.view', 'phase' => 1],
                    ['title' => 'Leads', 'route' => 'admin.leads.index', 'permission' => 'leads.view', 'phase' => 2],
                    ['title' => 'Follow-ups', 'route' => 'admin.follow-ups.index', 'permission' => 'leads.view', 'phase' => 2],
                ]],
                ['title' => 'Student Management', 'icon' => 'school', 'route' => 'admin.students.index', 'permission' => 'students.view', 'phase' => 2],
                ['title' => 'Counselor Management', 'icon' => 'support_agent', 'route' => 'admin.counselors.index', 'permission' => 'counselors.view', 'phase' => 2],
                ['title' => 'Student File Management', 'icon' => 'folder_shared', 'children' => [
                    ['title' => 'Files', 'route' => 'admin.files.index', 'permission' => 'files.view', 'phase' => 3],
                    ['title' => 'Document review', 'route' => 'admin.documents.index', 'permission' => 'documents.view', 'phase' => 3],
                    ['title' => 'Document categories', 'route' => 'admin.document-categories.index', 'permission' => 'settings.manage', 'phase' => 3],
                ]],
            ]],
            ['label' => 'Finance', 'items' => [
                ['title' => 'Payment & Accounts', 'icon' => 'account_balance_wallet', 'children' => [
                    ['title' => 'Invoices', 'route' => 'admin.payment-requests.index', 'permission' => 'payments.view', 'phase' => 4],
                    ['title' => 'Payments', 'route' => 'admin.payments.index', 'permission' => 'payments.view', 'phase' => 4],
                    ['title' => 'Pending verification', 'route' => 'admin.payments.pending', 'permission' => 'payments.verify', 'phase' => 4],
                    ['title' => 'Due payments', 'route' => 'admin.payment-requests.due', 'permission' => 'payments.view', 'phase' => 5],
                    ['title' => 'Transaction history', 'route' => 'admin.transactions.index', 'permission' => 'payments.view', 'phase' => 5],
                    ['title' => 'Gateway transactions', 'route' => 'admin.transactions.gateway', 'permission' => 'payments.view', 'phase' => 5],
                    ['title' => 'Expenses', 'route' => 'admin.expenses.index', 'permission' => 'expenses.manage', 'phase' => 5],
                    ['title' => 'Payment types', 'route' => 'admin.payment-types.index', 'permission' => 'payment-types.manage', 'phase' => 4],
                    ['title' => 'Payment methods', 'route' => 'admin.payment-methods.index', 'permission' => 'payment-types.manage', 'phase' => 4],
                ]],
                ['title' => 'Invoice & Refund Management', 'icon' => 'receipt_long', 'children' => [
                    ['title' => 'Refund requests', 'route' => 'admin.refunds.index', 'permission' => 'invoices.view', 'phase' => 5],
                    ['title' => 'Refund processing', 'route' => 'admin.refunds.processing', 'permission' => 'refunds.process', 'phase' => 5],
                    ['title' => 'Receipts', 'route' => 'admin.payments.receipts', 'permission' => 'invoices.view', 'phase' => 5],
                    ['title' => 'Partial payments', 'route' => 'admin.payment-requests.partial', 'permission' => 'payments.view', 'phase' => 5],
                ]],
            ]],
            ['label' => 'Services', 'items' => [
                ['title' => 'Visa Processing', 'icon' => 'approval', 'children' => [
                    ['title' => 'Applications', 'route' => 'admin.applications.index', 'permission' => 'applications.view', 'phase' => 6],
                    ['title' => 'Status updates', 'route' => 'admin.applications.updates', 'permission' => 'applications.update', 'phase' => 6],
                    ['title' => 'Visa types', 'route' => 'admin.visa-types.index', 'permission' => 'workflows.manage', 'phase' => 6],
                    ['title' => 'Workflows & stages', 'route' => 'admin.workflows.index', 'permission' => 'workflows.manage', 'phase' => 6],
                ]],
                ['title' => 'University Management', 'icon' => 'account_balance', 'children' => [
                    ['title' => 'All universities', 'route' => 'admin.universities.index', 'permission' => 'universities.view', 'phase' => 9],
                    ['title' => 'Add university', 'route' => 'admin.universities.create', 'permission' => 'universities.manage', 'phase' => 9],
                    ['title' => 'Countries', 'route' => 'admin.countries.index', 'permission' => 'universities.view', 'phase' => 9],
                ]],
                ['title' => 'Skill Migration', 'icon' => 'quiz', 'children' => [
                    ['title' => 'Country assessments', 'route' => 'admin.skill-assessments.index', 'permission' => 'assessments.view', 'phase' => 9],
                    ['title' => 'Question bank', 'route' => 'admin.skill-assessments.questions.index', 'permission' => 'assessments.manage', 'phase' => 9],
                    ['title' => 'Submissions', 'route' => 'admin.skill-assessments.submissions.index', 'permission' => 'assessments.view', 'phase' => 9],
                    ['title' => 'Countries', 'route' => 'admin.countries.index', 'permission' => 'assessments.manage', 'phase' => 9],
                ]],
                ['title' => 'Partner Management', 'icon' => 'handshake', 'children' => [
                    ['title' => 'Applications', 'route' => 'admin.partner-applications.index', 'permission' => 'partners.view', 'phase' => 9],
                    ['title' => 'Partners', 'route' => 'admin.partners.index', 'permission' => 'partners.view', 'phase' => 9],
                ]],
                ['title' => 'Working Visa', 'icon' => 'flight_takeoff', 'route' => 'admin.applications.working', 'permission' => 'working-visa.view', 'phase' => 6],
                ['title' => 'Exam & Work Management', 'icon' => 'work', 'route' => 'admin.applications.exams', 'permission' => 'exams.view', 'phase' => 6],
            ]],
            ['label' => 'Communication', 'items' => [
                ['title' => 'Email / Roundcube', 'icon' => 'mail', 'children' => [
                    ['title' => 'My email', 'route' => 'admin.email.index', 'permission' => 'email.access', 'phase' => 8],
                    ['title' => 'Compose', 'route' => 'admin.email.compose', 'permission' => 'email.access', 'phase' => 8],
                    ['title' => 'Email accounts', 'route' => 'admin.email.accounts.index', 'permission' => 'email.manage', 'phase' => 8],
                    ['title' => 'Templates', 'route' => 'admin.email.templates.index', 'permission' => 'email.manage', 'phase' => 8],
                    ['title' => 'Bulk email', 'route' => 'admin.email.bulk.index', 'permission' => 'email.manage', 'phase' => 8],
                    ['title' => 'Delivery logs', 'route' => 'admin.email.logs.index', 'permission' => 'email.manage', 'phase' => 8],
                    ['title' => 'Email settings', 'route' => 'admin.email.settings', 'permission' => 'email.manage', 'phase' => 8],
                ]],
                ['title' => 'Live Chat', 'icon' => 'forum', 'children' => [
                    ...($tawk ? [
                        ['title' => 'Tawk.to chat history', 'route' => 'admin.chat.index', 'permission' => 'chat.access', 'phase' => 7],
                        ['title' => 'Tawk.to offline messages', 'route' => 'admin.chat.offline', 'permission' => 'chat.access', 'phase' => 7],
                    ] : []),
                    ['title' => 'Student messages', 'route' => 'admin.chat.portal', 'permission' => 'chat.access', 'phase' => 7],
                    ['title' => 'Website chats', 'route' => 'admin.chat.guest', 'permission' => 'chat.access', 'phase' => 7],
                    ['title' => 'Moderation', 'route' => 'admin.chat.moderation', 'permission' => 'chat.moderate', 'phase' => 7],
                    ['title' => 'Chat security', 'route' => 'admin.chat.security', 'permission' => 'security.manage', 'phase' => 7],
                    ...($tawk ? [['title' => 'Tawk.to settings', 'route' => 'admin.chat.settings', 'permission' => 'chat.manage', 'phase' => 7]] : []),
                ]],
                ['title' => 'Ticket & Contact', 'icon' => 'confirmation_number', 'children' => [
                    ['title' => 'Tickets', 'route' => 'admin.tickets.index', 'permission' => 'tickets.view', 'phase' => 2],
                    ['title' => 'Contact Inquiries', 'route' => 'admin.inquiries.index', 'permission' => 'leads.view', 'phase' => 2],
                    ['title' => 'Ticket Categories', 'route' => 'admin.ticket-categories.index', 'permission' => 'tickets.manage', 'phase' => 2],
                ]],
            ]],
            ['label' => 'Website', 'items' => [
                ['title' => 'Blog Management', 'icon' => 'article', 'children' => [
                    ['title' => 'All posts', 'route' => 'admin.blog.posts.index', 'permission' => 'blog.view', 'phase' => 9],
                    ['title' => 'New post', 'route' => 'admin.blog.posts.create', 'permission' => 'blog.manage', 'phase' => 9],
                    ['title' => 'Categories', 'route' => 'admin.blog.taxonomy.index', 'params' => ['kind' => 'categories'], 'permission' => 'blog.view', 'phase' => 9],
                    ['title' => 'Tags', 'route' => 'admin.blog.taxonomy.index', 'params' => ['kind' => 'tags'], 'permission' => 'blog.view', 'phase' => 9],
                    ['title' => 'Comments', 'route' => 'admin.blog.comments.index', 'permission' => 'blog.view', 'phase' => 9],
                    ['title' => 'Authors', 'route' => 'admin.blog.authors.index', 'permission' => 'blog.view', 'phase' => 9],
                ]],
                ['title' => 'Page Management', 'icon' => 'web', 'route' => 'admin.pages.index', 'permission' => 'pages.view', 'phase' => 9],
                ['title' => 'Website CMS', 'icon' => 'design_services', 'children' => [
                    ['title' => 'Homepage', 'route' => 'admin.cms.homepage', 'permission' => 'cms.manage', 'phase' => 9],
                    ['title' => 'Site settings', 'route' => 'admin.cms.settings', 'permission' => 'cms.manage', 'phase' => 9],
                    ['title' => 'Menus', 'route' => 'admin.cms.menus.index', 'permission' => 'cms.manage', 'phase' => 9],
                    ['title' => 'FAQs', 'route' => 'admin.cms.faqs.index', 'permission' => 'cms.manage', 'phase' => 9],
                    ['title' => 'Testimonials', 'route' => 'admin.cms.testimonials.index', 'permission' => 'cms.manage', 'phase' => 9],
                    ['title' => 'Newsletter', 'route' => 'admin.cms.newsletter.index', 'permission' => 'cms.manage', 'phase' => 9],
                ]],
                ['title' => 'SEO Management', 'icon' => 'travel_explore', 'route' => null, 'permission' => 'seo.manage', 'phase' => 10],
            ]],
            ['label' => 'Insights', 'items' => [
                // Finance reports shipped with Phase 5; the rest of REQ §24 arrives in Phase 12.
                ['title' => 'Reports & Analytics', 'icon' => 'monitoring', 'children' => [
                    ['title' => 'Income & expenses', 'route' => 'admin.reports.financial', 'permission' => 'reports.financial', 'phase' => 5],
                    ['title' => 'Counselor-wise collection', 'route' => 'admin.reports.counselors', 'permission' => 'reports.financial', 'phase' => 5],
                    ['title' => 'Student-wise accounts', 'route' => 'admin.reports.students', 'permission' => 'reports.financial', 'phase' => 5],
                    ['title' => 'Export reports', 'route' => 'admin.reports.downloads', 'permission' => 'reports.export', 'phase' => 5],
                ]],
                ['title' => 'Notifications', 'icon' => 'notifications', 'route' => null, 'permission' => 'notifications.manage', 'phase' => 12],
                ['title' => 'Media Library', 'icon' => 'perm_media', 'route' => 'admin.media.index', 'permission' => 'media.view', 'phase' => 9],
            ]],
            ['label' => 'System', 'items' => [
                ['title' => 'Integrations', 'icon' => 'extension', 'route' => null, 'permission' => 'integrations.manage', 'phase' => 11],
                ['title' => 'Roles & Permissions', 'icon' => 'admin_panel_settings', 'route' => 'admin.roles.index', 'permission' => 'roles.manage', 'phase' => 1],
                ['title' => 'System Settings', 'icon' => 'settings', 'route' => null, 'permission' => 'settings.manage', 'phase' => 11],
                ['title' => 'Security & Audit Logs', 'icon' => 'shield_lock', 'route' => null, 'permission' => 'audit.view', 'phase' => 11],
            ]],
        ],

        // REQ §8.1: the 20 counselor dashboard menus.
        'counselor' => [
            ['label' => 'Workspace', 'items' => [
                ['title' => 'My Dashboard', 'icon' => 'dashboard', 'route' => 'counselor.dashboard', 'permission' => null, 'phase' => 4],
                ['title' => 'My Leads', 'icon' => 'person_search', 'route' => 'counselor.leads.index', 'permission' => 'leads.view', 'phase' => 2],
                ['title' => 'Assigned Students', 'icon' => 'school', 'route' => 'counselor.students.index', 'permission' => 'students.view', 'phase' => 2],
                ['title' => 'Create Student', 'icon' => 'person_add', 'route' => 'counselor.students.create', 'permission' => 'students.manage', 'phase' => 2],
            ]],
            ['label' => 'Student files', 'items' => [
                ['title' => 'Student File Management', 'icon' => 'folder_shared', 'route' => 'counselor.files.index', 'permission' => 'files.view', 'phase' => 3],
                ['title' => 'Student Documents', 'icon' => 'description', 'route' => 'counselor.documents.index', 'permission' => 'documents.view', 'phase' => 3],
                ['title' => 'Student Profile Update', 'icon' => 'manage_accounts', 'route' => 'counselor.profiles.index', 'permission' => 'students.manage', 'phase' => 3],
            ]],
            ['label' => 'Payments', 'items' => [
                ['title' => 'Payment Requests', 'icon' => 'request_quote', 'route' => 'counselor.payment-requests.index', 'permission' => 'payments.request', 'phase' => 4],
                ['title' => 'Payment History', 'icon' => 'history', 'route' => 'counselor.payments.index', 'permission' => 'payments.view', 'phase' => 4],
                ['title' => 'Due Payment List', 'icon' => 'pending_actions', 'route' => 'counselor.payment-requests.due', 'permission' => 'payments.view', 'phase' => 5],
                ['title' => 'Refund Requests', 'icon' => 'currency_exchange', 'route' => 'counselor.refunds.index', 'permission' => 'refunds.request', 'phase' => 5],
            ]],
            ['label' => 'Processing', 'items' => [
                ['title' => 'Visa Processing', 'icon' => 'approval', 'route' => 'counselor.applications.index', 'permission' => 'applications.view', 'phase' => 6],
                ['title' => 'Application Status Update', 'icon' => 'published_with_changes', 'route' => 'counselor.applications.updates', 'permission' => 'applications.update', 'phase' => 6],
                ['title' => 'Exam / Work Management', 'icon' => 'work', 'route' => 'counselor.applications.exams', 'permission' => 'exams.view', 'phase' => 6],
            ]],
            ['label' => 'Communication', 'items' => [
                ['title' => 'Student Communication', 'icon' => 'chat', 'route' => 'counselor.chat.portal', 'permission' => 'chat.access', 'phase' => 7],
                ['title' => 'Email', 'icon' => 'mail', 'route' => 'counselor.email.index', 'permission' => 'email.access', 'phase' => 8],
                ['title' => 'Live Chat', 'icon' => 'forum', 'children' => [
                    ...($tawk ? [
                        ['title' => 'Tawk.to chat history', 'route' => 'counselor.chat.index', 'permission' => 'chat.access', 'phase' => 7],
                        ['title' => 'Tawk.to offline messages', 'route' => 'counselor.chat.offline', 'permission' => 'chat.access', 'phase' => 7],
                    ] : []),
                    ['title' => 'Student messages', 'route' => 'counselor.chat.portal', 'permission' => 'chat.access', 'phase' => 7],
                    ['title' => 'Website chats', 'route' => 'counselor.chat.guest', 'permission' => 'chat.access', 'phase' => 7],
                ]],
                ['title' => 'Follow-up Management', 'icon' => 'event_repeat', 'route' => 'counselor.follow-ups.index', 'permission' => 'leads.update', 'phase' => 2],
            ]],
            ['label' => 'Account', 'items' => [
                ['title' => 'Reports', 'icon' => 'monitoring', 'route' => null, 'permission' => 'reports.view', 'phase' => 12],
                ['title' => 'My Profile', 'icon' => 'account_circle', 'route' => 'profile.edit', 'permission' => null, 'phase' => 0],
            ]],
        ],

        // REQ §9.1: the 20 accounts dashboard menus.
        'accounts' => [
            ['label' => 'Overview', 'items' => [
                ['title' => 'Accounts Dashboard', 'icon' => 'dashboard', 'route' => 'accounts.dashboard', 'permission' => null, 'phase' => 4],
                // Not one of REQ §9.1's 20 menus; Accounts staff have email.access (REQ §19.2 "Assign Email to User").
                ['title' => 'Email', 'icon' => 'mail', 'route' => 'accounts.email.index', 'permission' => 'email.access', 'phase' => 8],
            ]],
            ['label' => 'Payments', 'items' => [
                ['title' => 'All Payments', 'icon' => 'payments', 'route' => 'accounts.payments.index', 'permission' => 'payments.view', 'phase' => 4],
                ['title' => 'New Payment Requests', 'icon' => 'request_quote', 'route' => 'accounts.payment-requests.new', 'permission' => 'payments.view', 'phase' => 4],
                ['title' => 'Pending Verification', 'icon' => 'pending', 'route' => 'accounts.payments.pending', 'permission' => 'payments.verify', 'phase' => 4],
                ['title' => 'Payment Confirmation', 'icon' => 'task_alt', 'route' => 'accounts.payments.confirmation', 'permission' => 'payments.confirm', 'phase' => 4],
                ['title' => 'Rejected Transactions', 'icon' => 'block', 'route' => 'accounts.payments.rejected', 'permission' => 'payments.view', 'phase' => 4],
                ['title' => 'Due Payments', 'icon' => 'pending_actions', 'route' => 'accounts.payment-requests.due', 'permission' => 'payments.view', 'phase' => 5],
                ['title' => 'Partial Payments', 'icon' => 'donut_large', 'route' => 'accounts.payment-requests.partial', 'permission' => 'payments.view', 'phase' => 5],
            ]],
            ['label' => 'Refunds & invoices', 'items' => [
                ['title' => 'Refund Requests', 'icon' => 'currency_exchange', 'route' => 'accounts.refunds.index', 'permission' => 'invoices.view', 'phase' => 5],
                ['title' => 'Refund Processing', 'icon' => 'published_with_changes', 'route' => 'accounts.refunds.processing', 'permission' => 'refunds.process', 'phase' => 5],
                ['title' => 'Student Invoices', 'icon' => 'receipt_long', 'route' => 'accounts.payment-requests.index', 'permission' => 'invoices.view', 'phase' => 4],
                ['title' => 'Payment Receipts', 'icon' => 'receipt', 'route' => 'accounts.payments.receipts', 'permission' => 'invoices.view', 'phase' => 5],
                ['title' => 'Payment Methods', 'icon' => 'credit_card', 'route' => 'accounts.payment-methods.index', 'permission' => 'payment-types.manage', 'phase' => 4],
            ]],
            ['label' => 'Transactions', 'items' => [
                ['title' => 'Bank Transactions', 'icon' => 'account_balance', 'route' => 'accounts.transactions.bank', 'permission' => 'payments.view', 'phase' => 5],
                ['title' => 'Gateway Transactions', 'icon' => 'swap_horiz', 'route' => 'accounts.transactions.gateway', 'permission' => 'payments.view', 'phase' => 5],
                ['title' => 'Transaction History', 'icon' => 'history', 'route' => 'accounts.transactions.index', 'permission' => 'payments.view', 'phase' => 5],
                ['title' => 'Counselor-wise Collection', 'icon' => 'support_agent', 'route' => 'accounts.reports.counselors', 'permission' => 'reports.financial', 'phase' => 5],
                ['title' => 'Student-wise Accounts', 'icon' => 'school', 'route' => 'accounts.reports.students', 'permission' => 'reports.financial', 'phase' => 5],
            ]],
            ['label' => 'Reports', 'items' => [
                ['title' => 'Financial Reports', 'icon' => 'monitoring', 'route' => 'accounts.reports.financial', 'permission' => 'reports.financial', 'phase' => 5],
                ['title' => 'Expenses', 'icon' => 'shopping_cart', 'route' => 'accounts.expenses.index', 'permission' => 'expenses.manage', 'phase' => 5],
                ['title' => 'Export Reports', 'icon' => 'download', 'route' => 'accounts.reports.downloads', 'permission' => 'reports.export', 'phase' => 5],
            ]],
        ],

        // REQ §10.1: the student portal menus.
        'portal' => [
            ['label' => 'My account', 'items' => [
                ['title' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'portal.dashboard', 'permission' => null, 'phase' => 4],
                ['title' => 'My Profile', 'icon' => 'account_circle', 'route' => 'portal.profile.edit', 'permission' => null, 'phase' => 3],
                ['title' => 'My File', 'icon' => 'folder_shared', 'route' => 'portal.file', 'permission' => null, 'phase' => 3],
                ['title' => 'My Counselor', 'icon' => 'support_agent', 'route' => 'portal.counselor', 'permission' => null, 'phase' => 4],
                ['title' => 'Documents', 'icon' => 'description', 'route' => 'portal.documents.index', 'permission' => null, 'phase' => 3],
            ]],
            ['label' => 'Payments', 'items' => [
                ['title' => 'Payments', 'icon' => 'payments', 'route' => 'portal.payments.index', 'permission' => null, 'phase' => 4],
                ['title' => 'Payment History', 'icon' => 'history', 'route' => 'portal.payments.history', 'permission' => null, 'phase' => 4],
                ['title' => 'Due Payments', 'icon' => 'pending_actions', 'route' => 'portal.payments.due', 'permission' => null, 'phase' => 5],
                ['title' => 'Refunds', 'icon' => 'currency_exchange', 'route' => 'portal.refunds.index', 'permission' => null, 'phase' => 5],
            ]],
            ['label' => 'My application', 'items' => [
                ['title' => 'Visa Application', 'icon' => 'flight_takeoff', 'route' => 'portal.applications.index', 'permission' => null, 'phase' => 6],
                ['title' => 'Application Status', 'icon' => 'timeline', 'route' => 'portal.applications.status', 'permission' => null, 'phase' => 6],
            ]],
            ['label' => 'Communication', 'items' => [
                ['title' => 'Notifications', 'icon' => 'notifications', 'route' => 'notifications.index', 'permission' => null, 'phase' => 4],
                ['title' => 'Live Chat', 'icon' => 'forum', 'route' => 'portal.chat', 'permission' => null, 'phase' => 7],
                // REQ §10.1 "Access assigned email functionality if enabled": shown only to students granted email.access.
                ['title' => 'Email', 'icon' => 'mail', 'route' => 'portal.email.index', 'permission' => 'email.access', 'phase' => 8],
            ]],
        ],

    ],

];
