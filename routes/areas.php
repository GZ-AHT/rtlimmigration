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
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->withTrashed()->name('users.show');
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
        Route::resource('tickets', Admin\TicketController::class)->except(['edit', 'destroy']);

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
    });
