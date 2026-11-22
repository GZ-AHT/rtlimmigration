<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\MailboxMessage;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\Imap\ImapException;
use App\Support\Email\Imap\MailboxConnection;
use App\Support\Email\InboxSync;
use App\Support\Presenters\MailboxPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Received email (Inbox, Drafts, Spam, Trash) from the cPanel mailboxes over
 * IMAP, beside "Sent" in the Email area. The mailbox stays the source of
 * truth: delete, restore and read/unread are applied there too.
 */
class InboxController extends Controller
{
    use ResolvesAreaRoutes;

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'folder' => ['nullable', 'in:'.implode(',', MailboxMessage::FOLDERS)],
            'q' => ['nullable', 'string', 'max:100'],
            'account' => ['nullable', 'integer'],
        ]);
        $folder = $data['folder'] ?? 'inbox';
        $term = trim((string) ($data['q'] ?? ''));
        $visible = fn () => MailboxMessage::query()->visibleTo($user);

        $messages = $visible()->with('account:id,email')->where('folder', $folder)
            ->when(isset($data['account']), fn ($q) => $q->where('email_account_id', (int) $data['account']))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('from_email', 'like', $like)->orWhere('from_name', 'like', $like)->orWhere('preview', 'like', $like));
            })
            ->orderByDesc('received_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString()
            ->through(fn (MailboxMessage $m) => MailboxPresenter::row($m));

        $accounts = $this->accounts($request)->map(fn (EmailAccount $a) => [
            'id' => $a->id,
            'email' => $a->email,
            'ready' => app(ImapConnector::class)->ready($a),
            'synced_at' => Cache::get("inbox-synced:{$a->id}"),
            'error' => Cache::get("inbox-error:{$a->id}"),
        ]);

        return Inertia::render('email/Inbox', [
            'folder' => $folder,
            'q' => $term,
            'account' => isset($data['account']) ? (int) $data['account'] : null,
            'messages' => $messages,
            'counts' => [
                'inbox_unread' => $visible()->where('folder', 'inbox')->where('is_read', false)->count(),
                'drafts' => $visible()->where('folder', 'drafts')->count(),
                'spam' => $visible()->where('folder', 'spam')->count(),
                'trash' => $visible()->where('folder', 'trash')->count(),
            ],
            'accounts' => $accounts,
            'urls' => $this->urls($request),
        ]);
    }

    public function show(Request $request, MailboxMessage $mailboxMessage): Response
    {
        $message = $this->visible($request, $mailboxMessage);

        if (! $message->is_read) {
            $message->forceFill(['is_read' => true])->save();
            $this->remote($message, fn ($c) => $c->setSeen($message->remote_folder, $message->uid, true), quiet: true);
        }

        $message->loadMissing('account:id,email');

        return Inertia::render('email/Message', [
            'message' => MailboxPresenter::detail(
                $message,
                fn (int $i) => $this->areaRoute($request, 'email.inbox.attachment', [$message->id, $i]),
                fn (int $i) => $this->areaRoute($request, 'email.inbox.attachment', [$message->id, $i]).'?download=1',
            ),
            'urls' => [
                ...$this->urls($request),
                'reply' => $this->areaRoute($request, 'email.compose').'?inbox='.$message->id.'&mode=reply',
                'reply_all' => $this->areaRoute($request, 'email.compose').'?inbox='.$message->id.'&mode=reply_all',
                'forward' => $this->areaRoute($request, 'email.compose').'?inbox='.$message->id.'&mode=forward',
            ],
        ]);
    }

    /** Attachments download with a forced download; embedded pictures show inline (never SVG or HTML). */
    public function attachment(Request $request, MailboxMessage $mailboxMessage, int $index): StreamedResponse
    {
        $message = $this->visible($request, $mailboxMessage);
        $a = ($message->attachments ?? [])[$index] ?? null;

        abort_if($a === null || $a['path'] === '', 404);

        $disk = Storage::disk((string) config('documents.disk'));
        abort_unless($disk->exists($a['path']), 404);

        $safeImage = $a['inline'] && ! $request->boolean('download') && preg_match('#^image/(png|jpe?g|gif|webp)$#i', $a['mime']) === 1;

        return $disk->response($a['path'], $a['name'], [
            'Content-Type' => $safeImage ? strtolower($a['mime']) : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=0, no-store',
        ], $safeImage ? 'inline' : 'attachment');
    }

    public function read(Request $request, MailboxMessage $mailboxMessage): RedirectResponse
    {
        $message = $this->visible($request, $mailboxMessage);
        $read = $request->boolean('read');
        $message->forceFill(['is_read' => $read])->save();
        $this->remote($message, fn ($c) => $c->setSeen($message->remote_folder, $message->uid, $read), quiet: true);

        return back();
    }

    /** Moves to Trash; from Trash it is deleted for good. Done on the mail server first, so the two never disagree. */
    public function destroy(Request $request, MailboxMessage $mailboxMessage, InboxSync $sync): RedirectResponse
    {
        $message = $this->visible($request, $mailboxMessage);
        $permanent = $message->folder === 'trash';

        try {
            if ($permanent) {
                $this->remote($message, fn ($c) => $c->delete($message->remote_folder, $message->uid));
                $sync->discard($message);
            } else {
                $this->moveTo($message, 'trash');
            }
        } catch (ImapException $e) {
            $this->toast(['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        activity('email')->causedBy($request->user())->withProperties(['mailbox' => $message->account->email, 'folder' => $message->folder])
            ->log($permanent ? 'Deleted a received email for good' : 'Moved a received email to Trash');
        $this->toast(['type' => 'success', 'message' => $permanent ? __('Email deleted for good.') : __('Email moved to Trash.')]);

        return redirect($this->areaRoute($request, 'email.inbox').'?folder='.($permanent ? 'trash' : 'inbox'));
    }

    public function restore(Request $request, MailboxMessage $mailboxMessage): RedirectResponse
    {
        $message = $this->visible($request, $mailboxMessage);

        try {
            $this->moveTo($message, 'inbox');
        } catch (ImapException $e) {
            $this->toast(['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $this->toast(['type' => 'success', 'message' => __('Email moved to your Inbox.')]);

        return back();
    }

    /** The manual "Sync" button: the same code as the five-minute schedule. */
    public function sync(Request $request, InboxSync $sync, ImapConnector $connector): RedirectResponse
    {
        $accounts = $this->accounts($request)->filter(fn (EmailAccount $a) => $connector->ready($a));

        if ($accounts->isEmpty()) {
            $this->toast(['type' => 'error', 'message' => __('No mailbox is set up for receiving yet. An administrator needs to set its password and the IMAP server.')]);

            return back();
        }

        $imported = 0;
        $failed = [];

        foreach ($accounts as $account) {
            try {
                $imported += $sync->sync($account)['imported'];
            } catch (ImapException $e) {
                $failed[] = "{$account->email}: {$e->getMessage()}";
            }
        }

        $this->toast($failed === []
            ? ['type' => 'success', 'message' => $imported > 0 ? trans_choice('{1} 1 new email.|[2,*] :count new emails.', $imported) : __('Your inbox is up to date.')]
            : ['type' => 'error', 'message' => implode(' ', $failed)]);

        return back();
    }

    private function visible(Request $request, MailboxMessage $message): MailboxMessage
    {
        abort_unless(MailboxMessage::query()->visibleTo($request->user())->whereKey($message->id)->exists(), 403);

        return $message->loadMissing('account');
    }

    /** @return Collection<int, EmailAccount> */
    private function accounts(Request $request)
    {
        $user = $request->user();

        return EmailAccount::query()->where('status', 'active')
            ->when(! $user->can('email.manage'), fn ($q) => $q->whereHas('users', fn ($u) => $u->whereKey($user->id)))
            ->orderBy('email')->get();
    }

    /** @return array<string, string> */
    private function urls(Request $request): array
    {
        return [
            'inbox' => $this->areaRoute($request, 'email.inbox'),
            'sent' => $this->areaRoute($request, 'email.index'),
            'sync' => $this->areaRoute($request, 'email.inbox.sync'),
            'compose' => $this->areaRoute($request, 'email.compose'),
        ];
    }

    private function moveTo(MailboxMessage $message, string $role): void
    {
        $this->remote($message, function ($connection) use ($message, $role) {
            $target = $connection->folders()[$role] ?? null;

            if ($target === null) {
                throw new ImapException(__('The :folder folder was not found on the mail server.', ['folder' => $role]));
            }

            $uid = $connection->move($message->remote_folder, $message->uid, $target);

            if ($uid === null) {
                throw new ImapException(__('The mail server did not move the email. Please try again.'));
            }

            $message->forceFill(['folder' => $role, 'remote_folder' => $target, 'uid' => $uid])->save();
        });
    }

    /**
     * Runs something against the message's mailbox. $quiet: a failure only
     * means the mailbox is not told (the next sync fixes the flag), so it is not shown.
     *
     * @param  callable(MailboxConnection): mixed  $do
     */
    private function remote(MailboxMessage $message, callable $do, bool $quiet = false): void
    {
        try {
            $connection = app(ImapConnector::class)->connect($message->account);

            try {
                $do($connection);
            } finally {
                $connection->close();
            }
        } catch (ImapException $e) {
            if (! $quiet) {
                throw $e;
            }
        } catch (Throwable $e) {
            if (! $quiet) {
                throw new ImapException(__('The mail server returned an error.'), previous: $e);
            }
        }
    }
}
