<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use Illuminate\Http\Response;

/**
 * The unsubscribe link in bulk email. The `signed` middleware proves the link
 * came from one of our emails, so nobody can unsubscribe a stranger by
 * guessing an address. GET only shows a button (mail scanners and link
 * previews open links, and must not unsubscribe anyone); POST does it, and is
 * also what mail apps send for one-click unsubscribe (RFC 8058).
 */
class EmailUnsubscribeController extends Controller
{
    public function show(string $message): Response
    {
        return $this->page($this->find($message), done: false);
    }

    public function store(string $message): Response
    {
        $record = $this->find($message);
        EmailSuppression::add($record->to_email);

        return $this->page($record, done: true);
    }

    private function find(string $uuid): EmailMessage
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $uuid) === 1, 404);

        return EmailMessage::query()->where('uuid', $uuid)->whereNotNull('email_campaign_id')->firstOrFail();
    }

    private function page(EmailMessage $message, bool $done): Response
    {
        return response()->view('email.unsubscribe', ['email' => $message->to_email, 'done' => $done])
            ->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex']);
    }
}
