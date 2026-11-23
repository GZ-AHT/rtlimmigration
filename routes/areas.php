<?php

use App\Enums\Area;
use App\Http\Controllers\Accounts;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Applications;
use App\Http\Controllers\Counselor;
use App\Http\Controllers\Documents;
use App\Http\Controllers\Leads;
use App\Http\Controllers\Payments\GatewayController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\PaymentDocumentController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\Students;
use App\Models\Page;
use App\Support\Routing\AreaRoutes;
use Illuminate\Support\Facades\Route;

/*
| The four authenticated areas (REQ §30). Entering an area requires its
| "area.*" permission; each screen adds its own permission or policy check.
*/

Route::middleware(['auth', 'verified', 'can:'.Area::Admin->accessPermission()])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        // REQ §6 User Management
        Route::patch('users/{user}/restore', [Admin\UserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::patch('users/{user}/status', [Admin\UserController::class, 'status'])->name('users.status');
        Route::post('users/{user}/invitation', [Admin\UserController::class, 'invite'])->middleware('throttle:6,1')->name('users.invite');
        Route::post('users/{user}/password-reset', [Admin\UserController::class, 'sendPasswordReset'])->middleware('throttle:6,1')->name('users.password-reset');
        Route::post('users/{user}/verification', [Admin\UserController::class, 'sendVerification'])->middleware('throttle:6,1')->name('users.verification');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->withTrashed()->whereNumber('user')->name('users.show');
        Route::resource('users', Admin\UserController::class)->except('show');

        // REQ §6 Role & Permission Management
        Route::resource('roles', Admin\RoleController::class)->except('show');

        // REQ §7 / §11.1 Leads (all leads; Lead Manager and Super Admin)
        Route::get('follow-ups', [Leads\LeadController::class, 'followUps'])->name('follow-ups.index');
        Route::get('inquiries', [Leads\LeadController::class, 'inquiries'])->name('inquiries.index');
        AreaRoutes::leads(withAdminActions: true);

        // REQ §10 Students and §8 Counselors
        AreaRoutes::students();
        Route::patch('students/{student}/counselor', [Students\StudentController::class, 'assign'])->name('students.assign');
        Route::resource('counselors', Admin\CounselorController::class)->except('destroy');
        // REQ §13, §14, §17 applications; workflow and visa-type configuration
        Route::get('applications/working-visa', [Applications\ApplicationController::class, 'index'])->defaults('kind', 'work')->middleware('can:working-visa.view')->name('applications.working');
        AreaRoutes::applications();
        Route::middleware('can:workflows.manage')->group(function () {
            Route::get('visa-types', [Admin\VisaTypeController::class, 'index'])->name('visa-types.index');
            Route::post('visa-types', [Admin\VisaTypeController::class, 'store'])->name('visa-types.store');
            Route::put('visa-types/{visaType}', [Admin\VisaTypeController::class, 'update'])->name('visa-types.update');
            Route::delete('visa-types/{visaType}', [Admin\VisaTypeController::class, 'destroy'])->name('visa-types.destroy');
            Route::post('visa-types/{visaType}/requirements', [Admin\VisaTypeController::class, 'storeRequirement'])->name('visa-types.requirements.store');
            Route::delete('visa-types/{visaType}/requirements/{requirement}', [Admin\VisaTypeController::class, 'destroyRequirement'])->name('visa-types.requirements.destroy');

            Route::get('workflows', [Admin\WorkflowController::class, 'index'])->name('workflows.index');
            Route::post('workflows', [Admin\WorkflowController::class, 'store'])->name('workflows.store');
            Route::put('workflows/{workflow}', [Admin\WorkflowController::class, 'update'])->name('workflows.update');
            Route::delete('workflows/{workflow}', [Admin\WorkflowController::class, 'destroy'])->name('workflows.destroy');
            Route::post('workflows/{workflow}/stages', [Admin\WorkflowController::class, 'storeStage'])->name('workflows.stages.store');
            Route::put('workflows/{workflow}/stages/order', [Admin\WorkflowController::class, 'reorder'])->name('workflows.stages.reorder');
            Route::put('workflows/{workflow}/stages/{stage}', [Admin\WorkflowController::class, 'updateStage'])->name('workflows.stages.update');
            Route::delete('workflows/{workflow}/stages/{stage}', [Admin\WorkflowController::class, 'destroyStage'])->name('workflows.stages.destroy');
        });

        // REQ §12 payments, plus payment type / method settings
        AreaRoutes::payments(finance: true);
        Route::get('payment-types', [Admin\PaymentSettingsController::class, 'types'])->name('payment-types.index');
        Route::post('payment-types', [Admin\PaymentSettingsController::class, 'storeType'])->name('payment-types.store');
        Route::put('payment-types/{type}', [Admin\PaymentSettingsController::class, 'updateType'])->name('payment-types.update');
        Route::delete('payment-types/{type}', [Admin\PaymentSettingsController::class, 'destroyType'])->name('payment-types.destroy');
        Route::get('payment-methods', [Admin\PaymentSettingsController::class, 'methods'])->name('payment-methods.index');
        Route::post('payment-methods', [Admin\PaymentSettingsController::class, 'storeMethod'])->name('payment-methods.store');
        Route::put('payment-methods/{method}', [Admin\PaymentSettingsController::class, 'updateMethod'])->name('payment-methods.update');

        Route::get('document-categories', [Admin\DocumentCategoryController::class, 'index'])->name('document-categories.index');
        Route::post('document-categories', [Admin\DocumentCategoryController::class, 'store'])->name('document-categories.store');
        Route::put('document-categories/{category}', [Admin\DocumentCategoryController::class, 'update'])->name('document-categories.update');
        Route::delete('document-categories/{category}', [Admin\DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');

        // REQ §7 Tickets and contacts
        Route::get('ticket-categories', [Admin\TicketCategoryController::class, 'index'])->name('ticket-categories.index');
        Route::post('ticket-categories', [Admin\TicketCategoryController::class, 'store'])->name('ticket-categories.store');
        Route::put('ticket-categories/{category}', [Admin\TicketCategoryController::class, 'update'])->name('ticket-categories.update');
        Route::delete('ticket-categories/{category}', [Admin\TicketCategoryController::class, 'destroy'])->name('ticket-categories.destroy');
        Route::post('tickets/{ticket}/replies', [Admin\TicketController::class, 'reply'])->name('tickets.reply');
        Route::resource('tickets', Admin\TicketController::class)->except(['edit']);

        // REQ §18 Live chat (Tawk.to)
        Route::get('chat/settings', [Admin\ChatSettingsController::class, 'edit'])->name('chat.settings');
        Route::put('chat/settings', [Admin\ChatSettingsController::class, 'update'])->name('chat.settings.update');

        // REQ §11 chat security and moderation (Super Admin; moderation also chat.moderate)
        Route::get('chat/moderation', [Admin\ChatModerationController::class, 'index'])->name('chat.moderation');
        Route::post('chat/moderation/{message}', [Admin\ChatModerationController::class, 'decide'])->whereNumber('message')->middleware('throttle:120,1')->name('chat.moderation.decide');
        Route::get('chat/security', [Admin\ChatSecurityController::class, 'edit'])->name('chat.security');
        Route::put('chat/security', [Admin\ChatSecurityController::class, 'update'])->name('chat.security.update');
        Route::get('chat/security/activity', [Admin\ChatSecurityController::class, 'activity'])->name('chat.security.activity');
        Route::post('chat/security/blocks', [Admin\ChatSecurityController::class, 'block'])->name('chat.security.blocks.store');
        Route::delete('chat/security/blocks/{block}', [Admin\ChatSecurityController::class, 'unblock'])->whereNumber('block')->name('chat.security.blocks.destroy');
        Route::post('chat/security/guests/{guest}/suspension', [Admin\ChatSecurityController::class, 'suspend'])->whereNumber('guest')->name('chat.security.guests.suspend');
        Route::delete('chat/security/guests/{guest}/suspension', [Admin\ChatSecurityController::class, 'unsuspend'])->whereNumber('guest')->name('chat.security.guests.unsuspend');
        AreaRoutes::chat();

        // REQ §3 / §19 email: mailboxes on the cPanel server, templates, bulk email and delivery logs
        AreaRoutes::email();
        AreaRoutes::reports();

        // REQ §20 notification templates and settings
        Route::middleware('can:notifications.manage')->group(function () {
            Route::get('notifications/settings', [Admin\NotificationSettingsController::class, 'edit'])->name('notifications.settings');
            Route::put('notifications/settings', [Admin\NotificationSettingsController::class, 'update'])->name('notifications.settings.update');
        });
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('accounts', [Admin\EmailAccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [Admin\EmailAccountController::class, 'store'])->name('accounts.store');
            Route::post('accounts/sync', [Admin\EmailAccountController::class, 'sync'])->middleware('throttle:6,1')->name('accounts.sync');
            Route::put('accounts/{account}', [Admin\EmailAccountController::class, 'update'])->name('accounts.update');
            Route::put('accounts/{account}/username', [Admin\EmailAccountController::class, 'username'])->name('accounts.username');
            Route::put('accounts/{account}/password', [Admin\EmailAccountController::class, 'password'])->name('accounts.password');
            Route::patch('accounts/{account}/status', [Admin\EmailAccountController::class, 'status'])->name('accounts.status');
            Route::post('accounts/{account}/provision', [Admin\EmailAccountController::class, 'provision'])->name('accounts.provision');
            Route::post('accounts/{account}/test', [Admin\EmailAccountController::class, 'test'])->middleware('throttle:6,1')->name('accounts.test');
            Route::delete('accounts/{account}', [Admin\EmailAccountController::class, 'destroy'])->name('accounts.destroy');

            Route::get('settings', [Admin\EmailSettingsController::class, 'edit'])->name('settings');
            Route::put('settings', [Admin\EmailSettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/test', [Admin\EmailSettingsController::class, 'test'])->middleware('throttle:6,1')->name('settings.test');
            Route::post('settings/test-imap', [Admin\EmailConnectionTestController::class, 'imap'])->middleware('throttle:10,1')->name('settings.test-imap');
            Route::post('settings/test-smtp', [Admin\EmailConnectionTestController::class, 'smtp'])->middleware('throttle:10,1')->name('settings.test-smtp');

            Route::get('templates', [Admin\EmailTemplateController::class, 'index'])->name('templates.index');
            Route::post('templates', [Admin\EmailTemplateController::class, 'store'])->name('templates.store');
            Route::put('templates/{template}', [Admin\EmailTemplateController::class, 'update'])->name('templates.update');
            Route::delete('templates/{template}', [Admin\EmailTemplateController::class, 'destroy'])->name('templates.destroy');

            Route::get('bulk', [Admin\BulkEmailController::class, 'index'])->name('bulk.index');
            Route::post('bulk/preview', [Admin\BulkEmailController::class, 'preview'])->name('bulk.preview');
            Route::post('bulk', [Admin\BulkEmailController::class, 'store'])->middleware('throttle:6,1')->name('bulk.store');

            Route::get('unsubscribed', [Admin\EmailSuppressionController::class, 'index'])->name('unsubscribed.index');
            Route::post('unsubscribed', [Admin\EmailSuppressionController::class, 'store'])->middleware('throttle:30,1')->name('unsubscribed.store');
            Route::delete('unsubscribed/{suppression}', [Admin\EmailSuppressionController::class, 'destroy'])->whereNumber('suppression')->name('unsubscribed.destroy');
            Route::get('logs', [Admin\EmailLogController::class, 'index'])->name('logs.index');
            Route::get('logs/{message}', [Admin\EmailLogController::class, 'show'])->whereNumber('message')->name('logs.show');
            Route::post('logs/{message}/retry', [Admin\EmailLogController::class, 'retry'])->whereNumber('message')->name('logs.retry');
        });

        // REQ §21 / §23.4 website images (used by every editor's picker)
        Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
        Route::post('media', [Admin\MediaController::class, 'store'])->middleware('throttle:60,1')->name('media.store');
        Route::patch('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');

        // REQ §4 Blog Management
        Route::prefix('blog')->name('blog.')->group(function () {
            Route::get('posts', [Admin\Blog\BlogPostController::class, 'index'])->name('posts.index');
            Route::get('posts/create', [Admin\Blog\BlogPostController::class, 'create'])->name('posts.create');
            Route::post('posts', [Admin\Blog\BlogPostController::class, 'store'])->name('posts.store');
            Route::get('posts/{post}/edit', [Admin\Blog\BlogPostController::class, 'edit'])->name('posts.edit');
            Route::put('posts/{post}', [Admin\Blog\BlogPostController::class, 'update'])->name('posts.update');
            Route::patch('posts/{post}/feature', [Admin\Blog\BlogPostController::class, 'feature'])->name('posts.feature');
            Route::delete('posts/{post}', [Admin\Blog\BlogPostController::class, 'destroy'])->name('posts.destroy');
            Route::patch('posts/{post}/restore', [Admin\Blog\BlogPostController::class, 'restore'])->whereNumber('post')->name('posts.restore');
            Route::get('comments', [Admin\Blog\BlogCommentController::class, 'index'])->name('comments.index');
            Route::patch('comments/{comment}', [Admin\Blog\BlogCommentController::class, 'update'])->name('comments.update');
            Route::delete('comments/{comment}', [Admin\Blog\BlogCommentController::class, 'destroy'])->name('comments.destroy');
            Route::get('authors', [Admin\Blog\AuthorController::class, 'index'])->name('authors.index');
            Route::put('authors/{user}', [Admin\Blog\AuthorController::class, 'update'])->name('authors.update');
            Route::get('{kind}', [Admin\Blog\BlogTaxonomyController::class, 'index'])->whereIn('kind', ['categories', 'tags'])->name('taxonomy.index');
            Route::post('{kind}', [Admin\Blog\BlogTaxonomyController::class, 'store'])->whereIn('kind', ['categories', 'tags'])->name('taxonomy.store');
            Route::put('{kind}/{id}', [Admin\Blog\BlogTaxonomyController::class, 'update'])->whereIn('kind', ['categories', 'tags'])->whereNumber('id')->name('taxonomy.update');
            Route::delete('{kind}/{id}', [Admin\Blog\BlogTaxonomyController::class, 'destroy'])->whereIn('kind', ['categories', 'tags'])->whereNumber('id')->name('taxonomy.destroy');
        });

        // REQ §5 Page Management (section builder) and REQ §21 Website CMS
        Route::get('pages', [Admin\Cms\PageController::class, 'index'])->name('pages.index');
        Route::get('pages/create', [Admin\Cms\PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [Admin\Cms\PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}/edit', [Admin\Cms\PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [Admin\Cms\PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [Admin\Cms\PageController::class, 'destroy'])->name('pages.destroy');
        Route::patch('pages/{page}/restore', [Admin\Cms\PageController::class, 'restore'])->whereNumber('page')->name('pages.restore');
        Route::post('pages/{page}/revisions/{revision}', [Admin\Cms\PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
        Route::prefix('cms')->name('cms.')->group(function () {
            Route::get('homepage', fn () => redirect()->route('admin.pages.edit', Page::where('system_key', 'home')->firstOrFail()))->middleware('can:cms.manage')->name('homepage');
            Route::get('settings', [Admin\Cms\SiteSettingsController::class, 'edit'])->name('settings');
            Route::put('settings/{group}', [Admin\Cms\SiteSettingsController::class, 'update'])->name('settings.update');
            Route::get('menus', [Admin\Cms\MenuController::class, 'index'])->name('menus.index');
            Route::post('menus/{menu}/items', [Admin\Cms\MenuController::class, 'store'])->name('menus.items.store');
            Route::put('menus/{menu}/order', [Admin\Cms\MenuController::class, 'reorder'])->name('menus.reorder');
            Route::put('menu-items/{item}', [Admin\Cms\MenuController::class, 'update'])->name('menus.items.update');
            Route::delete('menu-items/{item}', [Admin\Cms\MenuController::class, 'destroy'])->name('menus.items.destroy');
            Route::get('faqs', [Admin\Cms\FaqController::class, 'index'])->name('faqs.index');
            Route::post('faqs', [Admin\Cms\FaqController::class, 'store'])->name('faqs.store');
            Route::put('faqs/order', [Admin\Cms\FaqController::class, 'reorder'])->name('faqs.reorder');
            Route::put('faqs/{faq}', [Admin\Cms\FaqController::class, 'update'])->name('faqs.update');
            Route::delete('faqs/{faq}', [Admin\Cms\FaqController::class, 'destroy'])->name('faqs.destroy');
            Route::post('faq-categories', [Admin\Cms\FaqController::class, 'storeCategory'])->name('faqs.categories.store');
            Route::delete('faq-categories/{category}', [Admin\Cms\FaqController::class, 'destroyCategory'])->name('faqs.categories.destroy');
            Route::get('testimonials', [Admin\Cms\TestimonialController::class, 'index'])->name('testimonials.index');
            Route::post('testimonials', [Admin\Cms\TestimonialController::class, 'store'])->name('testimonials.store');
            Route::put('testimonials/order', [Admin\Cms\TestimonialController::class, 'reorder'])->name('testimonials.reorder');
            Route::put('testimonials/{testimonial}', [Admin\Cms\TestimonialController::class, 'update'])->name('testimonials.update');
            Route::delete('testimonials/{testimonial}', [Admin\Cms\TestimonialController::class, 'destroy'])->name('testimonials.destroy');
            Route::get('newsletter', [Admin\Cms\NewsletterController::class, 'index'])->name('newsletter.index');
            Route::get('newsletter/export', [Admin\Cms\NewsletterController::class, 'export'])->name('newsletter.export');
            Route::delete('newsletter/{subscriber}', [Admin\Cms\NewsletterController::class, 'destroy'])->name('newsletter.destroy');
        });

        // REQ §23 System & advanced settings
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('settings', [Admin\SystemController::class, 'show'])->name('settings');
            Route::put('settings/{group}', [Admin\SystemController::class, 'update'])->name('settings.update');
            Route::post('cache/{type}', [Admin\SystemController::class, 'clearCache'])->middleware('throttle:20,1')->name('cache.clear');
            Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit');
            Route::get('backups', [Admin\BackupController::class, 'index'])->name('backups.index');
            Route::post('backups', [Admin\BackupController::class, 'store'])->middleware('throttle:6,1')->name('backups.store');
            Route::get('backups/{backup}/download', [Admin\BackupController::class, 'download'])->middleware('throttle:20,1')->name('backups.download');
            Route::post('backups/{backup}/restore', [Admin\BackupController::class, 'restore'])->middleware('throttle:3,10')->name('backups.restore');
            Route::delete('backups/{backup}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');
            Route::get('webhooks', [Admin\WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('webhooks', [Admin\WebhookController::class, 'store'])->name('webhooks.store');
            Route::put('webhooks/{webhook}', [Admin\WebhookController::class, 'update'])->name('webhooks.update');
            Route::post('webhooks/{webhook}/secret', [Admin\WebhookController::class, 'secret'])->name('webhooks.secret');
            Route::post('webhooks/{webhook}/test', [Admin\WebhookController::class, 'test'])->middleware('throttle:10,1')->name('webhooks.test');
            Route::delete('webhooks/{webhook}', [Admin\WebhookController::class, 'destroy'])->name('webhooks.destroy');
        });

        // REQ §22.3 Technical SEO
        Route::prefix('seo')->name('seo.')->group(function () {
            Route::get('redirects', [Admin\Cms\RedirectController::class, 'index'])->name('redirects.index');
            Route::post('redirects', [Admin\Cms\RedirectController::class, 'store'])->name('redirects.store');
            Route::put('redirects/{redirect}', [Admin\Cms\RedirectController::class, 'update'])->name('redirects.update');
            Route::delete('redirects/{redirect}', [Admin\Cms\RedirectController::class, 'destroy'])->name('redirects.destroy');
            Route::get('not-found', [Admin\Cms\NotFoundLogController::class, 'index'])->name('not-found.index');
            Route::delete('not-found', [Admin\Cms\NotFoundLogController::class, 'clear'])->name('not-found.clear');
            Route::delete('not-found/{log}', [Admin\Cms\NotFoundLogController::class, 'destroy'])->whereNumber('log')->name('not-found.destroy');
            Route::get('broken-links', [Admin\Cms\BrokenLinkController::class, 'index'])->name('broken-links.index');
            Route::post('broken-links/scan', [Admin\Cms\BrokenLinkController::class, 'scan'])->middleware('throttle:3,1')->name('broken-links.scan');
        });

        // REQ §15 University Management
        Route::get('universities', [Admin\Universities\UniversityController::class, 'index'])->name('universities.index');
        Route::get('universities/create', [Admin\Universities\UniversityController::class, 'create'])->name('universities.create');
        Route::post('universities', [Admin\Universities\UniversityController::class, 'store'])->name('universities.store');
        Route::get('universities/{university}/edit', [Admin\Universities\UniversityController::class, 'edit'])->name('universities.edit');
        Route::put('universities/{university}', [Admin\Universities\UniversityController::class, 'update'])->name('universities.update');
        Route::delete('universities/{university}', [Admin\Universities\UniversityController::class, 'destroy'])->name('universities.destroy');
        Route::post('universities/{university}/courses', [Admin\Universities\UniversityController::class, 'storeCourse'])->name('universities.courses.store');
        Route::put('universities/{university}/courses/{course}', [Admin\Universities\UniversityController::class, 'updateCourse'])->name('universities.courses.update');
        Route::delete('universities/{university}/courses/{course}', [Admin\Universities\UniversityController::class, 'destroyCourse'])->name('universities.courses.destroy');
        Route::get('countries', [Admin\Universities\CountryController::class, 'index'])->name('countries.index');
        Route::delete('countries/{country}', [Admin\Universities\CountryController::class, 'destroy'])->name('countries.destroy');
        Route::patch('countries/{country}', [Admin\Universities\CountryController::class, 'update'])->name('countries.update');

        // Country-based skill migration assessments
        Route::get('skill-assessments', [Admin\SkillAssessments\AssessmentController::class, 'index'])->name('skill-assessments.index');
        Route::post('skill-assessments', [Admin\SkillAssessments\AssessmentController::class, 'store'])->name('skill-assessments.store');
        Route::get('skill-assessments/questions', [Admin\SkillAssessments\QuestionController::class, 'index'])->name('skill-assessments.questions.index');
        Route::post('skill-assessments/questions', [Admin\SkillAssessments\QuestionController::class, 'store'])->name('skill-assessments.questions.store');
        Route::put('skill-assessments/questions/{question}', [Admin\SkillAssessments\QuestionController::class, 'update'])->name('skill-assessments.questions.update');
        Route::delete('skill-assessments/questions/{question}', [Admin\SkillAssessments\QuestionController::class, 'destroy'])->name('skill-assessments.questions.destroy');
        Route::post('skill-assessments/categories', [Admin\SkillAssessments\CategoryController::class, 'store'])->name('skill-assessments.categories.store');
        Route::put('skill-assessments/categories/{category}', [Admin\SkillAssessments\CategoryController::class, 'update'])->name('skill-assessments.categories.update');
        Route::delete('skill-assessments/categories/{category}', [Admin\SkillAssessments\CategoryController::class, 'destroy'])->name('skill-assessments.categories.destroy');
        Route::get('skill-assessments/submissions', [Admin\SkillAssessments\SubmissionController::class, 'index'])->name('skill-assessments.submissions.index');
        Route::get('skill-assessments/submissions/{submission}', [Admin\SkillAssessments\SubmissionController::class, 'show'])->name('skill-assessments.submissions.show');
        Route::delete('skill-assessments/submissions/{submission}', [Admin\SkillAssessments\SubmissionController::class, 'destroy'])->name('skill-assessments.submissions.destroy');
        Route::get('skill-assessments/{assessment}/edit', [Admin\SkillAssessments\AssessmentController::class, 'edit'])->name('skill-assessments.edit');
        Route::put('skill-assessments/{assessment}', [Admin\SkillAssessments\AssessmentController::class, 'update'])->name('skill-assessments.update');
        Route::delete('skill-assessments/{assessment}', [Admin\SkillAssessments\AssessmentController::class, 'destroy'])->name('skill-assessments.destroy');
        Route::post('countries', [Admin\Universities\CountryController::class, 'store'])->name('countries.store');

        // REQ §16 Partner Application Management
        Route::get('partner-applications', [Admin\Partners\PartnerApplicationController::class, 'index'])->name('partner-applications.index');
        Route::get('partner-applications/{application}', [Admin\Partners\PartnerApplicationController::class, 'show'])->name('partner-applications.show');
        Route::post('partner-applications/{application}/approve', [Admin\Partners\PartnerApplicationController::class, 'approve'])->name('partner-applications.approve');
        Route::post('partner-applications/{application}/reject', [Admin\Partners\PartnerApplicationController::class, 'reject'])->name('partner-applications.reject');
        Route::delete('partner-applications/{application}', [Admin\Partners\PartnerApplicationController::class, 'destroy'])->name('partner-applications.destroy');
        Route::get('partners', [Admin\Partners\PartnerController::class, 'index'])->name('partners.index');
        Route::get('partners/{partner}', [Admin\Partners\PartnerController::class, 'show'])->name('partners.show');
        Route::put('partners/{partner}', [Admin\Partners\PartnerController::class, 'update'])->name('partners.update');
        Route::patch('partners/{partner}/status', [Admin\Partners\PartnerController::class, 'status'])->name('partners.status');
        Route::post('partners/{partner}/documents', [Admin\Partners\PartnerController::class, 'storeDocument'])->name('partners.documents.store');
        Route::get('partner-documents/{document}', [Admin\Partners\PartnerController::class, 'document'])->name('partners.documents.show');
        Route::delete('partner-documents/{document}', [Admin\Partners\PartnerController::class, 'destroyDocument'])->name('partners.documents.destroy');
        Route::post('partners/{partner}/commissions', [Admin\Partners\PartnerController::class, 'storeCommission'])->name('partners.commissions.store');
        Route::patch('partner-commissions/{commission}', [Admin\Partners\PartnerController::class, 'updateCommission'])->name('partners.commissions.update');
        Route::post('partners/{partner}/payments', [Admin\Partners\PartnerController::class, 'storePayment'])->name('partners.payments.store');

        // Component reference for reviewing the panel design system.
        // Local environment only; never registered in production.
        if (app()->isLocal()) {
            Route::inertia('ui-kit', 'admin/UiKit')->name('ui-kit');
        }
    });

Route::middleware(['auth', 'verified', 'can:'.Area::Counselor->accessPermission()])
    ->prefix('counselor')->name('counselor.')
    ->group(function () {
        Route::get('/', Counselor\DashboardController::class)->name('dashboard');

        // REQ §8.1 My Leads, Follow-up Management, Assigned Students, Create Student
        Route::get('follow-ups', [Leads\LeadController::class, 'followUps'])->name('follow-ups.index');
        AreaRoutes::leads(withAdminActions: false);
        AreaRoutes::students();
        AreaRoutes::applications();
        AreaRoutes::payments(finance: false);
        AreaRoutes::chat();
        AreaRoutes::email();
        AreaRoutes::reports();
    });

Route::middleware(['auth', 'verified', 'can:'.Area::Accounts->accessPermission()])
    ->prefix('accounts')->name('accounts.')
    ->group(function () {
        Route::get('/', Accounts\DashboardController::class)->name('dashboard');

        // REQ §9.1 Accounts: requests, verification, confirmation
        AreaRoutes::payments(finance: true);
        Route::get('payment-methods', [Admin\PaymentSettingsController::class, 'methods'])->name('payment-methods.index');
        Route::post('payment-methods', [Admin\PaymentSettingsController::class, 'storeMethod'])->name('payment-methods.store');
        Route::put('payment-methods/{method}', [Admin\PaymentSettingsController::class, 'updateMethod'])->name('payment-methods.update');
        AreaRoutes::email();
        AreaRoutes::reports();
    });

Route::middleware(['auth', 'verified', 'can:'.Area::Portal->accessPermission()])
    ->prefix('portal')->name('portal.')
    ->group(function () {
        Route::get('/', Portal\DashboardController::class)->name('dashboard');

        // REQ §10.1 My Profile, My File, Documents (own records only)
        Route::get('profile', [Portal\PortalController::class, 'profile'])->name('profile.edit');
        Route::put('profile', [Portal\PortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('file', [Portal\PortalController::class, 'file'])->name('file');
        Route::get('documents', [Portal\PortalController::class, 'documents'])->name('documents.index');
        Route::post('documents', [Portal\PortalController::class, 'uploadDocument'])->middleware('throttle:20,1')->name('documents.store');
        Route::get('documents/{document}', [Documents\DocumentController::class, 'show'])->name('documents.show');
        Route::delete('documents/{document}', [Documents\DocumentController::class, 'destroy'])->name('documents.destroy');

        // REQ §10.1 Payments, Payment History, My Counselor
        Route::get('payments', [Portal\PortalPaymentController::class, 'index'])->name('payments.index');
        // SSLCommerz Easy Checkout posts here; the invoice is named in its payload. Must come before {paymentRequest}.
        Route::post('payments/checkout', [GatewayController::class, 'checkout'])->middleware('throttle:10,1')->name('payments.checkout');
        Route::post('payments/{paymentRequest}', [Portal\PortalPaymentController::class, 'store'])->middleware('throttle:10,1')->name('payments.store');
        Route::get('payments/due', [Portal\PortalPaymentController::class, 'index'])->name('payments.due');
        Route::post('payments/{paymentRequest}/online', [GatewayController::class, 'start'])->middleware('throttle:10,1')->name('payments.online');
        Route::get('payments/{paymentRequest}/invoice', [PaymentDocumentController::class, 'invoice'])->name('payments.invoice');
        Route::get('payment-history', [Portal\PortalPaymentController::class, 'history'])->name('payments.history');
        Route::get('payment-history/{payment}/evidence', [PaymentController::class, 'evidence'])->name('payments.evidence');
        Route::get('payment-history/{payment}/receipt', [PaymentDocumentController::class, 'receipt'])->name('payments.receipt');
        Route::get('refunds', [Portal\PortalPaymentController::class, 'refunds'])->name('refunds.index');
        Route::post('payment-history/{payment}/refund', [Portal\PortalPaymentController::class, 'requestRefund'])->middleware('throttle:5,1')->name('refunds.store');
        Route::get('counselor', [Portal\PortalPaymentController::class, 'counselor'])->name('counselor');

        // REQ §10.1 Visa Application, Application Status
        Route::get('applications', [Portal\PortalApplicationController::class, 'index'])->name('applications.index');
        Route::get('application-status', [Portal\PortalApplicationController::class, 'status'])->name('applications.status');

        // REQ §10.1 Live Chat
        Route::get('chat', Portal\PortalChatController::class)->name('chat');
        Route::get('chat/messages', [Portal\PortalChatController::class, 'messages'])->middleware('throttle:120,1')->name('chat.messages.index');
        Route::post('chat/messages', [Portal\PortalChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.messages.store');
        Route::get('chat/attachments/{attachment}', [Portal\PortalChatController::class, 'attachment'])->whereNumber('attachment')->name('chat.attachments.show');

        // REQ §10.1 Email: only for students an admin has granted email.access.
        AreaRoutes::email();
    });
