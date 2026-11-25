<?php

namespace App\Support\Routing;

use App\Http\Controllers\Accounts;
use App\Http\Controllers\Applications;
use App\Http\Controllers\Chat;
use App\Http\Controllers\Documents;
use App\Http\Controllers\Email;
use App\Http\Controllers\Leads;
use App\Http\Controllers\Payments;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Students;
use Illuminate\Support\Facades\Route;

/** Route groups mounted in more than one dashboard area. */
class AreaRoutes
{
    /**
     * Lead routes shared by the admin and counselor areas. Record access is
     * enforced by LeadPolicy; the admin-only actions (create, assign, delete)
     * are simply not mounted in the counselor area.
     */
    public static function leads(bool $withAdminActions): void
    {
        if ($withAdminActions) {
            Route::get('leads/create', [Leads\LeadController::class, 'create'])->name('leads.create');
            Route::post('leads', [Leads\LeadController::class, 'store'])->name('leads.store');
            Route::delete('leads/{lead}', [Leads\LeadController::class, 'destroy'])->name('leads.destroy');
            Route::post('leads/{lead}/assign', [Leads\LeadActionController::class, 'assign'])->name('leads.assign');
        }

        Route::get('leads', [Leads\LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [Leads\LeadController::class, 'show'])->name('leads.show');
        Route::get('leads/{lead}/edit', [Leads\LeadController::class, 'edit'])->name('leads.edit');
        Route::put('leads/{lead}', [Leads\LeadController::class, 'update'])->name('leads.update');
        Route::patch('leads/{lead}/status', [Leads\LeadActionController::class, 'status'])->name('leads.status');
        Route::post('leads/{lead}/notes', [Leads\LeadActionController::class, 'note'])->name('leads.notes.store');
        Route::post('leads/{lead}/contacts', [Leads\LeadActionController::class, 'contact'])->name('leads.contacts.store');
        Route::post('leads/{lead}/follow-ups', [Leads\LeadActionController::class, 'scheduleFollowUp'])->name('leads.follow-ups.store');
        Route::patch('leads/{lead}/follow-ups/{followUp}', [Leads\LeadActionController::class, 'completeFollowUp'])->name('leads.follow-ups.complete');
        Route::post('leads/{lead}/convert', [Leads\LeadActionController::class, 'convert'])->name('leads.convert');
    }

    public static function students(): void
    {
        Route::get('students', [Students\StudentController::class, 'index'])->name('students.index');
        Route::get('students/create', [Students\StudentController::class, 'create'])->name('students.create');
        Route::post('students', [Students\StudentController::class, 'store'])->name('students.store');
        Route::get('students/{student}', [Students\StudentController::class, 'show'])->name('students.show');

        // REQ §10 / §11.2 profile, notes, file opening, documents
        Route::get('students/{student}/profile', [Students\StudentProfileController::class, 'edit'])->name('students.profile.edit');
        Route::put('students/{student}/profile', [Students\StudentProfileController::class, 'update'])->name('students.profile.update');
        Route::post('students/{student}/notes', [Students\StudentNoteController::class, 'store'])->name('students.notes.store');
        Route::post('students/{student}/files', [Students\StudentFileController::class, 'store'])->name('students.files.store');
        Route::post('students/{student}/documents', [Documents\DocumentController::class, 'store'])->middleware('throttle:30,1')->name('students.documents.store');

        Route::get('profiles', [Students\StudentController::class, 'profiles'])->name('profiles.index');
        Route::get('files', [Students\StudentFileController::class, 'index'])->name('files.index');
        Route::get('files/{file}', [Students\StudentFileController::class, 'show'])->name('files.show');
        Route::put('files/{file}', [Students\StudentFileController::class, 'update'])->name('files.update');
        Route::patch('files/{file}/status', [Students\StudentFileController::class, 'status'])->name('files.status');

        Route::get('documents', Documents\DocumentQueueController::class)->name('documents.index');
        Route::get('documents/{document}', [Documents\DocumentController::class, 'show'])->name('documents.show');
        Route::patch('documents/{document}/review', [Documents\DocumentController::class, 'review'])->name('documents.review');
        Route::delete('documents/{document}', [Documents\DocumentController::class, 'destroy'])->name('documents.destroy');
    }

    /**
     * Applications of every visa type (REQ §13, §14, §17). The working-visa
     * and work-and-examination menus are the same screens, filtered.
     */
    public static function applications(): void
    {
        Route::get('applications', [Applications\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/status-updates', [Applications\ApplicationController::class, 'index'])->defaults('active', true)->middleware('can:applications.update')->name('applications.updates');
        Route::get('applications/exams', [Applications\ApplicationController::class, 'index'])->defaults('kind', 'exam')->middleware('can:exams.view')->name('applications.exams');
        Route::get('applications/create', [Applications\ApplicationController::class, 'create'])->name('applications.create');
        Route::post('applications', [Applications\ApplicationController::class, 'store'])->name('applications.store');
        Route::get('applications/{application}', [Applications\ApplicationController::class, 'show'])->name('applications.show');
        Route::put('applications/{application}', [Applications\ApplicationController::class, 'update'])->name('applications.update');
        // POST, not PATCH: the update can carry supporting documents.
        Route::post('applications/{application}/advance', [Applications\ApplicationController::class, 'advance'])->middleware('throttle:30,1')->name('applications.advance');
        Route::patch('applications/{application}/withdraw', [Applications\ApplicationController::class, 'withdraw'])->name('applications.withdraw');
        Route::post('applications/{application}/fee', [Applications\ApplicationController::class, 'fee'])->name('applications.fee');
    }

    /** Live chat history, offline messages, tags and assignment (REQ §18). */
    public static function chat(): void
    {
        Route::middleware('can:chat.access')->group(function () {
            Route::get('chat', [Chat\ConversationController::class, 'index'])->name('chat.index');
            Route::get('chat/offline-messages', [Chat\ConversationController::class, 'index'])->defaults('kind', 'offline')->name('chat.offline');
            Route::get('chat/student-messages', [Chat\ConversationController::class, 'index'])->defaults('kind', 'portal')->name('chat.portal');
            Route::get('chat/website-chats', [Chat\ConversationController::class, 'index'])->defaults('kind', 'guest')->name('chat.guest');
            Route::get('chat/{conversation}', [Chat\ConversationController::class, 'show'])->whereNumber('conversation')->name('chat.show');
            Route::patch('chat/{conversation}', [Chat\ConversationController::class, 'update'])->whereNumber('conversation')->name('chat.update');
            Route::post('chat/{conversation}/lead', [Chat\ConversationController::class, 'lead'])->whereNumber('conversation')->name('chat.lead');
            // Portal threads and website chats: polled every few seconds while open.
            Route::get('chat/{conversation}/messages', [Chat\ChatMessageController::class, 'index'])->whereNumber('conversation')->middleware('throttle:120,1')->name('chat.messages.index');
            Route::post('chat/{conversation}/messages', [Chat\ChatMessageController::class, 'store'])->whereNumber('conversation')->middleware('throttle:30,1')->name('chat.messages.store');
            Route::post('chat/{conversation}/typing', [Chat\ChatMessageController::class, 'typing'])->whereNumber('conversation')->middleware('throttle:60,1')->name('chat.typing');
            Route::get('chat/attachments/{attachment}', [Chat\ChatMessageController::class, 'attachment'])->whereNumber('attachment')->name('chat.attachments.show');
        });
    }

    /**
     * "My email" for staff (REQ §3, §19.1): assigned mailboxes, webmail
     * sign-in, signature and sending from inside the system.
     */
    public static function email(): void
    {
        Route::middleware('can:email.access')->group(function () {
            Route::get('email', [Email\MailboxController::class, 'index'])->name('email.index');
            Route::put('email/signature', [Email\MailboxController::class, 'signature'])->name('email.signature');
            Route::post('email/mailboxes/{account}/webmail', [Email\MailboxController::class, 'webmail'])->whereNumber('account')->middleware('throttle:10,1')->name('email.webmail');
            Route::delete('email/messages/{message}', [Email\MailboxController::class, 'trash'])->whereNumber('message')->name('email.messages.trash');
            Route::patch('email/messages/{message}/restore', [Email\MailboxController::class, 'restore'])->whereNumber('message')->name('email.messages.restore');
            // Received mail (IMAP): Inbox, Drafts, Spam, Trash.
            Route::get('email/inbox', [Email\InboxController::class, 'index'])->name('email.inbox');
            Route::post('email/inbox/sync', [Email\InboxController::class, 'sync'])->middleware('throttle:6,1')->name('email.inbox.sync');
            Route::get('email/inbox/{mailboxMessage}', [Email\InboxController::class, 'show'])->whereNumber('mailboxMessage')->name('email.inbox.show');
            Route::get('email/inbox/{mailboxMessage}/attachments/{index}', [Email\InboxController::class, 'attachment'])->whereNumber(['mailboxMessage', 'index'])->name('email.inbox.attachment');
            Route::patch('email/inbox/{mailboxMessage}/read', [Email\InboxController::class, 'read'])->whereNumber('mailboxMessage')->name('email.inbox.read');
            Route::patch('email/inbox/{mailboxMessage}/restore', [Email\InboxController::class, 'restore'])->whereNumber('mailboxMessage')->name('email.inbox.restore');
            Route::delete('email/inbox/{mailboxMessage}', [Email\InboxController::class, 'destroy'])->whereNumber('mailboxMessage')->name('email.inbox.destroy');
            Route::get('email/compose', [Email\ComposeController::class, 'create'])->name('email.compose');
            Route::post('email/compose', [Email\ComposeController::class, 'store'])->middleware('throttle:30,1')->name('email.send');
        });
    }

    /**
     * Payment requests and payments (REQ §12). Finance areas (admin,
     * accounts) also get verification, confirmation and cash entry.
     */
    public static function payments(bool $finance): void
    {
        Route::get('payment-requests', [Payments\PaymentRequestController::class, 'index'])->name('payment-requests.index');
        Route::get('payment-requests/create', [Payments\PaymentRequestController::class, 'create'])->name('payment-requests.create');
        Route::post('payment-requests', [Payments\PaymentRequestController::class, 'store'])->name('payment-requests.store');
        Route::get('payment-requests/{paymentRequest}', [Payments\PaymentRequestController::class, 'show'])->name('payment-requests.show');
        Route::patch('payment-requests/{paymentRequest}/cancel', [Payments\PaymentRequestController::class, 'cancel'])->name('payment-requests.cancel');
        Route::post('payment-requests/{paymentRequest}/payments', [Payments\PaymentController::class, 'store'])->middleware('throttle:20,1')->name('payment-requests.payments.store');

        Route::get('payment-requests/{paymentRequest}/invoice', [Payments\PaymentDocumentController::class, 'invoice'])->name('payment-requests.invoice');
        // REQ §8.1 "Due Payment List", §9.1 "Due Payments"
        Route::get('invoices/due', [Payments\PaymentRequestController::class, 'index'])->defaults('status', 'open')->name('payment-requests.due');

        Route::get('payments', [Payments\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [Payments\PaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/evidence', [Payments\PaymentController::class, 'evidence'])->name('payments.evidence');
        Route::get('payments/{payment}/receipt', [Payments\PaymentDocumentController::class, 'receipt'])->name('payments.receipt');

        // REQ §8.1 / §9.1 refunds: anyone here may ask; approval and payout are checked by policy.
        Route::get('refunds', [Payments\RefundController::class, 'index'])->name('refunds.index');
        Route::get('refunds/{refund}', [Payments\RefundController::class, 'show'])->name('refunds.show');
        Route::post('payments/{payment}/refunds', [Payments\RefundController::class, 'store'])->middleware('throttle:20,1')->name('payments.refunds.store');
        Route::patch('refunds/{refund}/approve', [Payments\RefundController::class, 'approve'])->name('refunds.approve');
        Route::patch('refunds/{refund}/reject', [Payments\RefundController::class, 'reject'])->name('refunds.reject');

        if ($finance) {
            Route::get('invoices/new', [Payments\PaymentRequestController::class, 'index'])->defaults('status', 'unpaid')->name('payment-requests.new');
            Route::post('payment-requests/{paymentRequest}/received', [Payments\PaymentController::class, 'recordReceived'])->name('payment-requests.received');
            Route::get('payments-queue/pending', [Payments\PaymentController::class, 'index'])->defaults('status', 'pending')->name('payments.pending');
            Route::get('payments-queue/confirmation', [Payments\PaymentController::class, 'index'])->defaults('status', 'verified')->name('payments.confirmation');
            Route::get('payments-queue/rejected', [Payments\PaymentController::class, 'index'])->defaults('status', 'rejected')->name('payments.rejected');
            Route::patch('payments/{payment}/verify', [Payments\PaymentController::class, 'verify'])->name('payments.verify');
            Route::patch('payments/{payment}/confirm', [Payments\PaymentController::class, 'confirm'])->name('payments.confirm');
            Route::patch('payments/{payment}/internal-note', [Payments\PaymentController::class, 'internalNote'])->name('payments.internal-note');
            Route::patch('payments/{payment}/reject', [Payments\PaymentController::class, 'reject'])->name('payments.reject');

            // REQ §9.1 menus 8, 10, 12 and 14–20
            Route::get('invoices/partial', [Payments\PaymentRequestController::class, 'index'])->defaults('status', 'partial')->name('payment-requests.partial');
            Route::get('receipts', [Payments\PaymentController::class, 'index'])->defaults('status', 'confirmed')->name('payments.receipts');
            Route::get('refund-processing', [Payments\RefundController::class, 'index'])->defaults('status', 'approved')->name('refunds.processing');
            Route::patch('refunds/{refund}/process', [Payments\RefundController::class, 'process'])->name('refunds.process');
            Route::get('transactions', [Payments\TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/bank', [Payments\PaymentController::class, 'index'])->defaults('kind', 'bank_transfer')->name('transactions.bank');
            Route::get('transactions/gateway', [Payments\TransactionController::class, 'gateway'])->name('transactions.gateway');

            Route::middleware('can:expenses.manage')->group(function () {
                Route::get('expenses', [Accounts\ExpenseController::class, 'index'])->name('expenses.index');
                Route::post('expenses', [Accounts\ExpenseController::class, 'store'])->name('expenses.store');
                // POST, not PUT: PHP only parses file uploads on POST.
                Route::post('expenses/{expense}', [Accounts\ExpenseController::class, 'update'])->name('expenses.update');
                Route::delete('expenses/{expense}', [Accounts\ExpenseController::class, 'destroy'])->name('expenses.destroy');
                Route::get('expenses/{expense}/attachment', [Accounts\ExpenseController::class, 'attachment'])->name('expenses.attachment');
                Route::post('expense-categories', [Accounts\ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
            });

            Route::middleware('can:reports.financial')->group(function () {
                Route::get('reports/financial', [Reports\FinancialReportController::class, 'index'])->name('reports.financial');
                Route::get('reports/counselors', [Reports\FinancialReportController::class, 'counselors'])->name('reports.counselors');
                Route::get('reports/students', [Reports\FinancialReportController::class, 'students'])->name('reports.students');
            });

            Route::middleware('can:reports.export')->group(function () {
                Route::get('reports/exports', [Reports\FinancialReportController::class, 'downloads'])->name('reports.downloads');
                Route::get('reports/exports/{report}/{format}', [Reports\FinancialReportController::class, 'download'])->middleware('throttle:30,1')->name('reports.download');
            });
        }
    }

    /** REQ §24: the report hub, each report and its downloads. */
    public static function reports(): void
    {
        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [Reports\OperationalReportController::class, 'hub'])->name('reports.hub');
            Route::get('reports/view/{report}', [Reports\OperationalReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');
            Route::get('reports/view/{report}/{format}', [Reports\OperationalReportController::class, 'download'])->where('report', '[a-z-]+')->where('format', 'xlsx|csv|pdf')->middleware('throttle:30,1')->name('reports.file');
        });
    }
}
