<?php

use App\Http\Controllers\Chat\ChatWebhookController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payments\GatewayController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Website\EnquiryController;
use App\Http\Controllers\Website\GuestChatController;
use Illuminate\Support\Facades\Route;

// Placeholder until the Figma website is built in Phase 9.
Route::inertia('/', 'Welcome')->name('home');

// REQ §7 website lead form (full Figma Contact page arrives in Phase 9).
Route::get('contact', [EnquiryController::class, 'show'])->name('contact');
Route::post('enquiry', [EnquiryController::class, 'store'])->middleware('throttle:5,1')->name('enquiry.store');

// Signed account invitation link (REQ §11.2).
Route::get('invitation/{user}/{hash}', InvitationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('invitation.accept');

// REQ §12.3 / §12.5 payment gateway callbacks. Public and CSRF-exempt
// (bootstrap/app.php): the gateway posts here, and each call only triggers a
// server-to-server validation with the gateway.
Route::post('payments/gateway/{gateway}/ipn', [GatewayController::class, 'ipn'])->middleware('throttle:120,1')->name('gateway.ipn');
Route::match(['get', 'post'], 'payments/gateway/{gateway}/return/{outcome}', [GatewayController::class, 'return'])
    ->whereIn('outcome', ['success', 'fail', 'cancel'])
    ->middleware('throttle:60,1')
    ->name('gateway.return');

// REQ §18 Tawk.to webhooks. Public and CSRF-exempt (bootstrap/app.php);
// every request must carry a valid X-Tawk-Signature.
Route::post('chat/webhook/tawk', ChatWebhookController::class)->middleware('throttle:240,1')->name('chat.webhook');

// REQ §11 website chat for visitors without an account. CSRF-protected like
// every web route; the visitor's chat is found from their session cookie.
Route::get('chat', [GuestChatController::class, 'show'])->name('chat.guest');
Route::post('chat/start', [GuestChatController::class, 'start'])->middleware('throttle:10,1')->name('chat.guest.start');
Route::get('chat/messages', [GuestChatController::class, 'messages'])->middleware('throttle:120,1')->name('chat.guest.messages.index');
Route::post('chat/messages', [GuestChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.guest.messages.store');
Route::get('chat/attachments/{attachment}', [GuestChatController::class, 'attachment'])->whereNumber('attachment')->middleware('throttle:60,1')->name('chat.guest.attachments.show');
Route::post('chat/link', [GuestChatController::class, 'link'])->middleware(['auth', 'verified', 'throttle:10,1'])->name('chat.guest.link');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    // Shared by all four dashboard areas.
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/areas.php';
require __DIR__.'/settings.php';
