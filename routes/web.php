<?php

use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\Auth\PasswordOtpController;
use App\Http\Controllers\Chat\ChatWebhookController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Email\EmailTrackingController;
use App\Http\Controllers\Email\EmailUnsubscribeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\Payments\GatewayController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Website\BlogController;
use App\Http\Controllers\Website\ConsultationController;
use App\Http\Controllers\Website\EnquiryController;
use App\Http\Controllers\Website\GuestChatController;
use App\Http\Controllers\Website\NewsletterController;
use App\Http\Controllers\Website\PageController;
use App\Http\Controllers\Website\PartnerApplicationController;
use App\Http\Controllers\Website\SeoController;
use App\Http\Controllers\Website\SkillMigrationController;
use App\Http\Controllers\Website\UniversityController;
use App\Http\Middleware\ShareWebsiteData;
use App\Http\Middleware\TrackPageView;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public website (REQ §21, Figma RTLIMMIG). Pages are built in the CMS; a
// catch-all for custom page slugs is registered last, at the end of this file.
Route::middleware([ShareWebsiteData::class, TrackPageView::class])->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('contact', [PageController::class, 'contact'])->name('contact');
    Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('sitemap-{type}.xml', [SeoController::class, 'sitemapOf'])->whereIn('type', SeoController::SITEMAPS)->name('sitemap.type');
    Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');
    Route::get('universities/{slug}', [UniversityController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('universities.show');

    // Country-based skill migration self-assessment.
    Route::get('skill-migration', [SkillMigrationController::class, 'index'])->name('skill-migration.index');
    Route::get('skill-migration/take/{token}', [SkillMigrationController::class, 'take'])->where('token', '[A-Za-z0-9]{48}')->name('skill-migration.take');
    Route::get('skill-migration/result/{token}', [SkillMigrationController::class, 'result'])->where('token', '[A-Za-z0-9]{48}')->name('skill-migration.result');
    Route::get('skill-migration/{slug}', [SkillMigrationController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('skill-migration.show');
});
Route::post('skill-migration/{slug}/start', [SkillMigrationController::class, 'start'])->where('slug', '[a-z0-9-]+')->middleware(['throttle:10,1', 'recaptcha'])->name('skill-migration.start');
Route::post('skill-migration/take/{token}', [SkillMigrationController::class, 'submit'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1')->name('skill-migration.submit');

// REQ §7 website lead form: every enquiry becomes a lead.
Route::post('enquiry', [EnquiryController::class, 'store'])->middleware(['throttle:5,1', 'recaptcha'])->name('enquiry.store');
Route::post('blog/{slug}/comments', [BlogController::class, 'comment'])->where('slug', '[a-z0-9-]+')->middleware(['throttle:5,1', 'recaptcha'])->name('blog.comments.store');
Route::post('partners/apply', [PartnerApplicationController::class, 'store'])->middleware(['throttle:5,1', 'recaptcha'])->name('partners.apply');
Route::post('newsletter', [NewsletterController::class, 'store'])->middleware(['throttle:5,1', 'recaptcha'])->name('newsletter.store');
Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:20,1')->name('newsletter.unsubscribe');

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
    // The gateway sends the payer's browser here cross-site, so there is no session to use. Starting
    // one would set a new guest cookie that replaces the student's real login cookie.
    ->withoutMiddleware([StartSession::class, AddQueuedCookiesToResponse::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->name('gateway.return');

// REQ §18 Tawk.to webhooks. Public and CSRF-exempt (bootstrap/app.php);
// every request must carry a valid X-Tawk-Signature.
Route::post('chat/webhook/tawk', ChatWebhookController::class)->middleware('throttle:240,1')->name('chat.webhook');

// REQ §3 email tracking: opens (1x1 image) and clicks (signed redirect) on mail this system sent.
Route::get('email/o/{message}.gif', [EmailTrackingController::class, 'open'])->middleware('throttle:120,1')->name('email.track.open');
Route::get('email/unsubscribe/{message}', [EmailUnsubscribeController::class, 'show'])->middleware(['signed', 'throttle:30,1'])->name('email.unsubscribe');
Route::post('email/unsubscribe/{message}', [EmailUnsubscribeController::class, 'store'])->middleware(['signed', 'throttle:30,1'])->name('email.unsubscribe.store');
Route::get('email/c/{message}', [EmailTrackingController::class, 'click'])->middleware(['signed', 'throttle:120,1'])->name('email.track.click');

// REQ §11 website chat for visitors without an account. CSRF-protected like
// every web route; the visitor's chat is found from their session cookie.
// Video consultation booking popup (verify email, pick a time) and the customer's signed manage page.
Route::post('consultation/code', [ConsultationController::class, 'sendCode'])->middleware('throttle:6,1')->name('consultation.code');
Route::post('consultation/verify', [ConsultationController::class, 'verifyCode'])->middleware('throttle:12,1')->name('consultation.verify');
Route::get('consultation/availability', [ConsultationController::class, 'availability'])->middleware('throttle:60,1')->name('consultation.availability');
Route::post('consultation/book', [ConsultationController::class, 'book'])->middleware('throttle:10,1')->name('consultation.book');
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('consultation/manage/{reference}', [ConsultationController::class, 'manage'])->middleware(ShareWebsiteData::class)->where('reference', 'CB-[A-Z0-9]{6}')->name('consultation.manage');
    Route::post('consultation/manage/{reference}/cancel', [ConsultationController::class, 'cancel'])->where('reference', 'CB-[A-Z0-9]{6}')->name('consultation.manage.cancel');
    Route::post('consultation/manage/{reference}/reschedule', [ConsultationController::class, 'reschedule'])->where('reference', 'CB-[A-Z0-9]{6}')->name('consultation.manage.reschedule');
});

Route::get('chat', [GuestChatController::class, 'show'])->middleware(ShareWebsiteData::class)->name('chat.guest');
Route::get('chat/widget', [GuestChatController::class, 'widget'])->middleware('throttle:60,1')->name('chat.guest.widget');
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
    Route::put('notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/areas.php';
require __DIR__.'/settings.php';

// Custom CMS pages (/about-us, /faq, /student-visa…). Registered last so
// every other route wins; single-segment slugs only.
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware([ShareWebsiteData::class, TrackPageView::class])
    ->name('page');

// Six-digit codes for email verification and "forgot password". The Fortify
// routes password.request / password.email / verification.send still exist
// (see FortifyServiceProvider); these are the extra steps.
Route::middleware('auth')->post('email/verify/otp', [EmailOtpController::class, 'verify'])
    ->middleware('throttle:otp')->name('verification.otp');

Route::middleware('guest')->prefix('forgot-password')->name('password.otp')->group(function () {
    Route::get('code', [PasswordOtpController::class, 'code']);
    Route::post('code', [PasswordOtpController::class, 'verify'])->middleware('throttle:otp')->name('.verify');
    Route::post('resend', [PasswordOtpController::class, 'resend'])->middleware('throttle:otp')->name('.resend');
    Route::get('new-password', [PasswordOtpController::class, 'reset'])->name('.reset');
    Route::post('new-password', [PasswordOtpController::class, 'update'])->middleware('throttle:otp')->name('.update');
});
