<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** REQ §3 "Email Tracking (if supported)": open pixel and signed click redirect. */
class EmailTrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $message): Response
    {
        $this->record($message, 'open');

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** The `signed` middleware has checked that this exact target came from one of our emails. */
    public function click(Request $request, string $message): RedirectResponse
    {
        $url = (string) $request->query('u');
        abort_unless(preg_match('#^https?://#i', $url) === 1, 404);

        $this->record($message, 'click');

        return redirect()->away($url);
    }

    private function record(string $uuid, string $kind): void
    {
        if (! preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return;
        }

        $message = fn () => EmailMessage::query()->where('uuid', $uuid);
        $now = now();

        $message()->increment("{$kind}_count");
        $message()->whereNull("{$kind}ed_at")->update(["{$kind}ed_at" => $now]);

        // A click proves the email was opened even when images were blocked.
        if ($kind === 'click') {
            $message()->whereNull('opened_at')->update(['opened_at' => $now]);
        }
    }
}
