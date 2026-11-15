<?php

namespace App\Support\Routing;

use App\Http\Controllers\Documents;
use App\Http\Controllers\Leads;
use App\Http\Controllers\Payments;
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

        Route::get('payments', [Payments\PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [Payments\PaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/evidence', [Payments\PaymentController::class, 'evidence'])->name('payments.evidence');

        if ($finance) {
            Route::get('invoices/new', [Payments\PaymentRequestController::class, 'index'])->defaults('status', 'unpaid')->name('payment-requests.new');
            Route::post('payment-requests/{paymentRequest}/received', [Payments\PaymentController::class, 'recordReceived'])->name('payment-requests.received');
            Route::get('payments-queue/pending', [Payments\PaymentController::class, 'index'])->defaults('status', 'pending')->name('payments.pending');
            Route::get('payments-queue/confirmation', [Payments\PaymentController::class, 'index'])->defaults('status', 'verified')->name('payments.confirmation');
            Route::get('payments-queue/rejected', [Payments\PaymentController::class, 'index'])->defaults('status', 'rejected')->name('payments.rejected');
            Route::patch('payments/{payment}/verify', [Payments\PaymentController::class, 'verify'])->name('payments.verify');
            Route::patch('payments/{payment}/confirm', [Payments\PaymentController::class, 'confirm'])->name('payments.confirm');
            Route::patch('payments/{payment}/reject', [Payments\PaymentController::class, 'reject'])->name('payments.reject');
        }
    }
}
