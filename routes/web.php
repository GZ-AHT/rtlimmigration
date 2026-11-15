<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Website\EnquiryController;
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
