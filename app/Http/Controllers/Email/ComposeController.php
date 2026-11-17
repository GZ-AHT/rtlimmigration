<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Support\Email\EmailSender;
use App\Support\Email\SenderAccounts;
use App\Support\Email\TemplateRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    public function create(Request $request): Response
    {
        $user = $request->user();
        $preset = $request->validate([
            'student' => ['nullable', 'integer'],
            'lead' => ['nullable', 'integer'],
        ]);

        $recipient = $this->resolveRecipient($user, isset($preset['student']) ? 'student' : (isset($preset['lead']) ? 'lead' : null), $preset['student'] ?? $preset['lead'] ?? null);

        return Inertia::render('email/Compose', [
            'senders' => SenderAccounts::options($user),
            'templates' => EmailTemplate::query()->where('is_active', true)->orderBy('category')->orderBy('name')->get()->map(fn (EmailTemplate $t) => [
                'id' => $t->id, 'name' => $t->name, 'category' => $t->category, 'subject' => $t->subject, 'body' => $t->body,
            ]),
            'recipient' => $recipient ? $this->describe($recipient) : null,
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

        $signature = ($data['include_signature'] ?? true) ? $user->emailSignature?->body : null;
        $uuid = (string) Str::uuid();

        $message = $sender->queue([
            'email_account_id' => $accountId,
            'email_template_id' => $data['email_template_id'] ?? null,
            'sent_by' => $user->id,
            'recipient_type' => $recipient?->getMorphClass(),
            'recipient_id' => $recipient?->getKey(),
            'to_email' => $to,
            'to_name' => $recipient ? $vars['name'] : ($data['to_name'] ?? null),
            'subject' => $renderer->fill($data['subject'], $vars),
            'body' => $renderer->toHtml($renderer->fill($data['body'], $vars), $signature),
            'attachments' => $this->storeAttachments($request->file('files', []), $uuid),
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
