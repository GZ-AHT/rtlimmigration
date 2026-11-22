<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\MailboxMessage;
use App\Models\Student;
use App\Models\User;
use App\Support\Email\EmailSender;
use App\Support\Email\SenderAccounts;
use App\Support\Email\TemplateRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Compose from inside the system (REQ §3 Compose, Templates, Attachments),
 * so the email lands in the student's or lead's email history. Mail written
 * in Roundcube stays in the mailbox instead.
 */
class ComposeController extends Controller
{
    use ResolvesAreaRoutes;

    public const MAX_ATTACHMENTS = 5;

    /** Most addresses in each of CC and BCC. */
    public const MAX_COPIES = 10;

    public function create(Request $request): Response
    {
        $user = $request->user();
        $preset = $request->validate([
            'student' => ['nullable', 'integer'],
            'lead' => ['nullable', 'integer'],
            'reply' => ['nullable', 'integer'],
            'forward' => ['nullable', 'integer'],
            // Reply or forward an email received in a mailbox (Inbox).
            'inbox' => ['nullable', 'integer'],
            'mode' => ['nullable', 'in:reply,reply_all,forward'],
        ]);

        $received = isset($preset['inbox'])
            ? MailboxMessage::query()->visibleTo($user)->with('account')->findOrFail((int) $preset['inbox'])
            : null;

        $source = isset($preset['reply']) || isset($preset['forward'])
            ? $this->ownMessage($user, (int) ($preset['reply'] ?? $preset['forward']))
            : null;
        $forwarding = $source !== null && isset($preset['forward']);

        $recipient = $source !== null
            ? ($forwarding ? null : $this->recipientOf($user, $source))
            : $this->resolveRecipient($user, isset($preset['student']) ? 'student' : (isset($preset['lead']) ? 'lead' : null), $preset['student'] ?? $preset['lead'] ?? null);

        return Inertia::render('email/Compose', [
            'senders' => SenderAccounts::options($user),
            'templates' => EmailTemplate::query()->where('is_active', true)->orderBy('category')->orderBy('name')->get()->map(fn (EmailTemplate $t) => [
                'id' => $t->id, 'name' => $t->name, 'category' => $t->category, 'subject' => $t->subject, 'body' => $t->body,
            ]),
            'recipient' => $recipient ? $this->describe($recipient) : null,
            'prefill' => $received
                ? $this->prefillReceived($user, $received, $preset['mode'] ?? 'reply')
                : ($source ? $this->prefill($source, $forwarding) : null),
            'placeholders' => TemplateRenderer::PLACEHOLDERS,
            'signature' => (string) $user->emailSignature?->body,
            'limits' => [
                'max_files' => self::MAX_ATTACHMENTS,
                'max_mb' => (int) floor(config('documents.max_size_kb') / 1024),
                'extensions' => array_map('strval', array_keys(config('documents.allowed'))),
            ],
            'urls' => [
                'send' => $this->areaRoute($request, 'email.send'),
                'back' => $this->areaRoute($request, 'email.index'),
            ],
        ]);
    }

    public function store(Request $request, EmailSender $sender, TemplateRenderer $renderer): RedirectResponse
    {
        $user = $request->user();
        $allowed = config('documents.allowed');

        $data = $request->validate([
            'email_account_id' => ['nullable', 'integer'],
            'recipient_type' => ['nullable', Rule::in(['student', 'lead'])],
            'recipient_id' => ['nullable', 'required_with:recipient_type', 'integer'],
            'to_email' => ['required_without:recipient_type', 'nullable', 'email:rfc', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:120'],
            'cc' => ['nullable', 'string', 'max:1500'],
            'bcc' => ['nullable', 'string', 'max:1500'],
            'forward_of' => ['nullable', 'integer'],
            'forward_of_inbox' => ['nullable', 'integer'],
            'email_template_id' => ['nullable', 'integer', Rule::exists(EmailTemplate::class, 'id')],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'include_signature' => ['boolean'],
            'files' => ['array', 'max:'.self::MAX_ATTACHMENTS],
            'files.*' => ['file', 'max:'.config('documents.max_size_kb'), 'mimes:'.implode(',', array_keys($allowed))],
        ], [
            'files.*.mimes' => __('Attach PDF, image or Word files only.'),
            'to_email.required_without' => __('Enter who the email is to.'),
        ]);

        $accountId = isset($data['email_account_id']) ? (int) $data['email_account_id'] : null;
        if (! SenderAccounts::allows($user, $accountId)) {
            throw ValidationException::withMessages(['email_account_id' => __('You cannot send from this mailbox.')]);
        }

        $recipient = $this->resolveRecipient($user, $data['recipient_type'] ?? null, $data['recipient_id'] ?? null)
            ?? $this->matchRecipient($user, (string) ($data['to_email'] ?? ''));

        $vars = $renderer->variables($recipient, $user, $data['to_name'] ?? null, $data['to_email'] ?? null);
        $to = $recipient ? $vars['email'] : (string) $data['to_email'];

        if (blank($to)) {
            throw ValidationException::withMessages(['to_email' => __('This person has no email address on file.')]);
        }

        $cc = $this->addresses($data['cc'] ?? '', 'cc', [$to]);
        $bcc = $this->addresses($data['bcc'] ?? '', 'bcc', [$to, ...$cc]);

        $signature = ($data['include_signature'] ?? true) ? $user->emailSignature?->body : null;
        $uuid = (string) Str::uuid();
        $forwarded = isset($data['forward_of']) ? $this->ownMessage($user, (int) $data['forward_of']) : null;
        $uploaded = $this->storeAttachments($request->file('files', []), $uuid) ?? [];
        $forwardedFromInbox = isset($data['forward_of_inbox'])
            ? MailboxMessage::query()->visibleTo($user)->findOrFail((int) $data['forward_of_inbox'])
            : null;
        $attachments = [...$uploaded, ...$this->copyAttachments($forwarded, $uuid), ...$this->copyReceivedAttachments($forwardedFromInbox, $uuid)];

        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages(['files' => __('Attach at most :max files, including those carried over from the forwarded email.', ['max' => self::MAX_ATTACHMENTS])]);
        }

        $message = $sender->queue([
            'email_account_id' => $accountId,
            'email_template_id' => $data['email_template_id'] ?? null,
            'sent_by' => $user->id,
            'recipient_type' => $recipient?->getMorphClass(),
            'recipient_id' => $recipient?->getKey(),
            'to_email' => $to,
            'to_name' => $recipient ? $vars['name'] : ($data['to_name'] ?? null),
            'cc' => $cc ?: null,
            'bcc' => $bcc ?: null,
            'subject' => $renderer->fill($data['subject'], $vars),
            'body' => $renderer->toHtml($renderer->fill($data['body'], $vars), $signature),
            'attachments' => $attachments ?: null,
        ]);

        $this->toast(['type' => 'success', 'message' => __('Email to :to queued for sending.', ['to' => $message->to_email])]);

        return redirect($this->areaRoute($request, 'email.index'));
    }

    /** A preset recipient must be someone this user may see. */
    private function resolveRecipient(User $user, ?string $type, int|string|null $id): ?Model
    {
        if ($type === null || $id === null) {
            return null;
        }

        $model = $type === 'student' ? Student::with('user')->find($id) : Lead::find($id);
        abort_if($model === null, 404);
        abort_unless($user->can('view', $model), 403);

        return $model;
    }

    /** Typed addresses are linked to a student or lead the user can see, so the email shows in their history. */
    private function matchRecipient(User $user, string $email): ?Model
    {
        if ($email === '') {
            return null;
        }

        $student = Student::with('user')->whereHas('user', fn ($q) => $q->where('email', $email))->first();
        if ($student && $user->can('view', $student)) {
            return $student;
        }

        $lead = Lead::query()->where('email_normalized', strtolower(trim($email)))->whereNull('student_id')->latest('id')->first();

        return $lead && $user->can('view', $lead) ? $lead : null;
    }

    /** @return array{type: string, id: int, name: string, email: string|null} */
    private function describe(Model $recipient): array
    {
        return $recipient instanceof Student
            ? ['type' => 'student', 'id' => $recipient->id, 'name' => $recipient->user->name, 'email' => $recipient->user->email]
            : ['type' => 'lead', 'id' => (int) $recipient->getKey(), 'name' => (string) $recipient->getAttribute('name'), 'email' => $recipient->getAttribute('email')];
    }

    /** Reply and forward start from one of the user's own sent emails, never anyone else's. */
    private function ownMessage(User $user, int $id): EmailMessage
    {
        return EmailMessage::query()->where('sent_by', $user->id)->with('recipient')->findOrFail($id);
    }

    /** The student or lead the original went to, if the user may still see them. */
    private function recipientOf(User $user, EmailMessage $message): ?Model
    {
        $recipient = $message->recipient;

        return ($recipient instanceof Student || $recipient instanceof Lead) && $user->can('view', $recipient) ? $recipient : null;
    }

    /** @return array{mode: string, to_email: string|null, to_name: string|null, cc: string, subject: string, body: string, forward_of: int|null, attachments: list<string>} */
    private function prefill(EmailMessage $source, bool $forwarding): array
    {
        $original = $this->plainText($source->body);
        $when = ($source->sent_at ?? $source->created_at)?->format('D, j M Y H:i');
        $who = trim(($source->from_name ? $source->from_name.' ' : '').'<'.$source->from_email.'>');

        if ($forwarding) {
            $body = "\n\n---------- Forwarded message ----------\nFrom: {$who}\nDate: {$when}\nSubject: {$source->subject}\nTo: {$source->to_email}\n\n{$original}";
        } else {
            $quoted = implode("\n", array_map(fn (string $line) => '> '.$line, explode("\n", $original)));
            $body = "\n\nOn {$when}, {$who} wrote:\n{$quoted}";
        }

        $prefix = $forwarding ? 'Fwd: ' : 'Re: ';

        return [
            'mode' => $forwarding ? 'forward' : 'reply',
            'to_email' => $forwarding ? null : $source->to_email,
            'to_name' => $forwarding ? null : $source->to_name,
            'cc' => $forwarding ? '' : implode(', ', $source->cc ?? []),
            'subject' => preg_match('/^\s*(re|fwd?)\s*:/i', $source->subject) === 1 ? $source->subject : $prefix.$source->subject,
            'body' => $body,
            'forward_of' => $forwarding ? $source->id : null,
            'attachments' => $forwarding ? array_map(fn (array $a) => $a['name'], $source->attachments ?? []) : [],
        ];
    }

    /** @return array{mode: string, to_email: string|null, to_name: string|null, cc: string, subject: string, body: string, forward_of: null, forward_of_inbox: int|null, email_account_id: string, attachments: list<string>} */
    private function prefillReceived(User $user, MailboxMessage $m, string $mode): array
    {
        $forwarding = $mode === 'forward';
        $original = filled($m->body_text) ? trim((string) $m->body_text) : $this->plainText((string) $m->body_html);
        $when = $m->received_at?->format('D, j M Y H:i');
        $who = trim(($m->from_name ? $m->from_name.' ' : '').'<'.$m->from_email.'>');
        $subject = (string) $m->subject;

        if ($forwarding) {
            $body = "\n\n---------- Forwarded message ----------\nFrom: {$who}\nDate: {$when}\nSubject: {$subject}\nTo: ".implode(', ', $m->to ?? [])."\n\n{$original}";
        } else {
            $quoted = implode("\n", array_map(fn (string $line) => '> '.$line, explode("\n", $original)));
            $body = "\n\nOn {$when}, {$who} wrote:\n{$quoted}";
        }

        $to = $m->reply_to ?: $m->from_email;
        $own = strtolower($m->account->email);
        $cc = $mode === 'reply_all'
            ? array_values(array_filter(array_unique([...($m->to ?? []), ...($m->cc ?? [])]), fn (string $a) => strtolower($a) !== $own && strtolower($a) !== strtolower((string) $to)))
            : [];
        // Reply from the mailbox the email arrived at, when this user may send from it.
        $account = SenderAccounts::allows($user, $m->email_account_id) && SenderAccounts::for($user)->contains('id', $m->email_account_id) ? (string) $m->email_account_id : '';

        return [
            'mode' => $forwarding ? 'forward' : 'reply',
            'to_email' => $forwarding ? null : $to,
            'to_name' => $forwarding ? null : $m->from_name,
            'cc' => implode(', ', array_slice($cc, 0, self::MAX_COPIES)),
            'subject' => preg_match('/^\s*(re|fwd?)\s*:/i', $subject) === 1 ? $subject : ($forwarding ? 'Fwd: ' : 'Re: ').$subject,
            'body' => $body,
            'forward_of' => null,
            'forward_of_inbox' => $forwarding ? $m->id : null,
            'email_account_id' => $account,
            'attachments' => $forwarding ? array_values(array_map(fn (array $a) => $a['name'], array_filter($m->attachments ?? [], fn (array $a) => ! $a['inline'] && $a['path'] !== ''))) : [],
        ];
    }

    /**
     * Attachments of a received email being forwarded, copied into the new email's own folder.
     *
     * @return list<array{name: string, path: string, mime: string, size: int}>
     */
    private function copyReceivedAttachments(?MailboxMessage $source, string $uuid): array
    {
        $disk = Storage::disk((string) config('documents.disk'));
        $copies = [];

        foreach ($source === null ? [] : ($source->attachments ?? []) as $a) {
            if ($a['inline'] || $a['path'] === '' || ! $disk->exists($a['path'])) {
                continue;
            }

            $path = "email-attachments/{$uuid}/".basename($a['path']);
            $disk->copy($a['path'], $path);
            $copies[] = ['name' => $a['name'], 'path' => $path, 'mime' => $a['mime'], 'size' => $a['size']];
        }

        return $copies;
    }

    private function plainText(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6]|tr)>/i'], "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? $text);
    }

    /**
     * CC and BCC arrive as one text box: addresses separated by commas,
     * semicolons or spaces. Each must be a real address; repeats, and anyone
     * already getting the email another way, are dropped.
     *
     * @param  list<string>  $already
     * @return list<string>
     */
    private function addresses(string $input, string $field, array $already): array
    {
        $found = [];

        foreach (preg_split('/[\s,;]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false || strlen($address) > 255) {
                throw ValidationException::withMessages([$field => __(':address is not a valid email address.', ['address' => Str::limit($address, 60)])]);
            }

            $found[strtolower($address)] ??= $address;
        }

        $taken = array_map('strtolower', $already);
        $list = array_values(array_filter($found, fn (string $a, string $key) => ! in_array($key, $taken, true), ARRAY_FILTER_USE_BOTH));

        if (count($list) > self::MAX_COPIES) {
            throw ValidationException::withMessages([$field => __('Add at most :max addresses.', ['max' => self::MAX_COPIES])]);
        }

        return $list;
    }

    /**
     * A forwarded email keeps its attachments: each file is copied into the
     * new email's own folder, so deleting one email never breaks the other.
     *
     * @return list<array{name: string, path: string, mime: string, size: int}>
     */
    private function copyAttachments(?EmailMessage $source, string $uuid): array
    {
        $disk = Storage::disk((string) config('documents.disk'));
        $copies = [];

        foreach ($source === null ? [] : ($source->attachments ?? []) as $a) {
            if (! $disk->exists($a['path'])) {
                continue;
            }

            $path = "email-attachments/{$uuid}/".basename($a['path']);
            $disk->copy($a['path'], $path);
            $copies[] = [...$a, 'path' => $path];
        }

        return $copies;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<array{name: string, path: string, mime: string, size: int}>|null
     */
    private function storeAttachments(array $files, string $uuid): ?array
    {
        if ($files === []) {
            return null;
        }

        $stored = [];

        foreach ($files as $file) {
            $path = $file->store("email-attachments/{$uuid}", config('documents.disk'));

            if ($path === false) {
                throw ValidationException::withMessages(['files' => __('An attachment could not be saved. Please try again.')]);
            }

            $stored[] = [
                'name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
                'path' => $path,
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }
}
