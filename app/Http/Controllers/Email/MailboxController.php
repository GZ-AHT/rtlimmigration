<?php

namespace App\Http\Controllers\Email;

use App\Enums\EmailMessageStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Support\Email\CpanelClient;
use App\Support\Email\CpanelException;
use App\Support\Email\EmailSettings;
use App\Support\Presenters\EmailPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My email" for staff (REQ §19.1, §3): the mailboxes assigned to them,
 * one-click webmail sign-in, their signature and what they sent from here.
 */
class MailboxController extends Controller
{
    use ResolvesAreaRoutes;

    /** cPanel's Roundcube, opened directly instead of the webmail app chooser. */
    public const ROUNDCUBE_PATH = '/3rdparty/roundcube/';

    public function index(Request $request, EmailSettings $settings): Response
    {
        $user = $request->user();
        $view = $request->validate(['view' => ['nullable', 'in:sent,trash']])['view'] ?? 'sent';
        $mine = fn () => EmailMessage::query()->where('sent_by', $user->id);

        $accounts = $user->emailAccounts()->orderBy('email')->get()->map(fn (EmailAccount $a) => [
            ...EmailPresenter::account($a),
            'can_send' => (bool) $a->getRelationValue('pivot')?->getAttribute('can_send') && $a->canSendSmtp(),
            'webmail_url' => $a->on_server && $a->isActive() ? $this->areaRoute($request, 'email.webmail', $a) : null,
        ]);

        return Inertia::render('email/Index', [
            'accounts' => $accounts,
            'webmailReady' => $settings->cpanelConfigured(),
            // For setting up a phone or desktop mail app. Passwords come from an admin, never from here.
            'servers' => $accounts->isNotEmpty() && $settings->imap()['host'] !== '' ? ['imap' => $settings->imap(), 'smtp' => $settings->smtp()] : null,
            'signature' => (string) $user->emailSignature?->body,
            'view' => $view,
            'trashCount' => $mine()->whereNotNull('trashed_at')->count(),
            'sent' => $mine()->when($view === 'trash', fn ($q) => $q->whereNotNull('trashed_at'), fn ($q) => $q->whereNull('trashed_at'))
                ->with('recipient')->latest('id')->paginate(15)->withQueryString()->through(fn (EmailMessage $m) => EmailPresenter::message($m)),
            'urls' => [
                'compose' => $this->areaRoute($request, 'email.compose'),
                'signature' => $this->areaRoute($request, 'email.signature'),
                'message' => $this->areaRoute($request, 'email.index'),
            ],
            'canManage' => $user->can('email.manage'),
            // Webmail opens with a plain form post into a new tab, outside Inertia, so it needs the token itself.
            'csrfToken' => csrf_token(),
        ]);
    }

    public function signature(Request $request): RedirectResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:2000']]);
        $user = $request->user();

        if (blank($data['body'] ?? null)) {
            $user->emailSignature()->delete();
        } else {
            $user->emailSignature()->updateOrCreate([], ['body' => trim($data['body'])]);
        }

        $this->toast(['type' => 'success', 'message' => __('Signature saved.')]);

        return back();
    }

    /**
     * Removes an email from "Sent from the system". Only the dashboard copy is
     * hidden (it can be restored); the delivery log, the person's history and
     * anything in the mailbox are untouched. Emails still waiting to send stay.
     */
    public function trash(Request $request, EmailMessage $message): RedirectResponse
    {
        abort_unless($message->sent_by === $request->user()->id, 403);

        if ($message->status === EmailMessageStatus::Queued) {
            $this->toast(['type' => 'error', 'message' => __('This email is still waiting to be sent.')]);

            return back();
        }

        $message->forceFill(['trashed_at' => now()])->save();
        activity('email')->causedBy($request->user())->withProperties(['message' => $message->uuid])->log('Removed an email from the sent list');
        $this->toast(['type' => 'success', 'message' => __('Email removed from your sent list. You can restore it from Deleted.')]);

        return back();
    }

    public function restore(Request $request, EmailMessage $message): RedirectResponse
    {
        abort_unless($message->sent_by === $request->user()->id, 403);

        $message->forceFill(['trashed_at' => null])->save();
        activity('email')->causedBy($request->user())->withProperties(['message' => $message->uuid])->log('Restored an email to the sent list');
        $this->toast(['type' => 'success', 'message' => __('Email restored to your sent list.')]);

        return back();
    }

    /** REQ §19.3: Laravel → Roundcube single sign-on through a one-time cPanel webmail session. */
    public function webmail(Request $request, EmailAccount $account, CpanelClient $cpanel, EmailSettings $settings): HttpResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->emailAccounts()->whereKey($account->id)->exists(), 403, __('This mailbox is not assigned to you.'));

        if (! $account->isActive() || ! $account->on_server) {
            $this->toast(['type' => 'error', 'message' => __('This mailbox is suspended or not on the mail server yet.')]);

            return back();
        }

        try {
            $session = $cpanel->webmailSession($account->local_part, $account->domain, $request->ip());
        } catch (CpanelException $e) {
            report($e);
            $this->toast(['type' => 'error', 'message' => __('Webmail could not be opened: :error', ['error' => $e->getMessage()])]);

            return back();
        }

        activity('email')->causedBy($user)->performedOn($account)->withProperties(['email' => $account->email, 'ip' => $request->ip()])->log('Signed in to webmail');

        return response()->view('email.webmail', [
            'email' => $account->email,
            'action' => $settings->webmailUrl().$session['token'].'/login/',
            'session' => $session['session'],
            'goto' => self::ROUNDCUBE_PATH,
        ])->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
