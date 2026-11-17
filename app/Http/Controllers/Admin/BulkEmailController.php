<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FileStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\Student;
use App\Support\CounselorDirectory;
use App\Support\Email\EmailSender;
use App\Support\Email\SenderAccounts;
use App\Support\Email\TemplateRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §3 Bulk Email: one personalised email per student or lead, sent through the queue. */
class BulkEmailController extends Controller
{
    /** A larger send should go through a newsletter service, not a mailbox's SMTP limits. */
    public const MAX_RECIPIENTS = 2000;

    public function index(Request $request): Response
    {
        Gate::authorize('email.manage');

        return Inertia::render('admin/email/Bulk', [
            'campaigns' => EmailCampaign::query()->with(['creator:id,name', 'account:id,email'])->latest('id')->paginate(15)->through(fn (EmailCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subject' => $c->subject,
                'from' => $c->account?->email,
                'audience' => $this->describe($c->audience),
                'total' => $c->total,
                'sent' => $c->sent,
                'failed' => $c->failed,
                'creator' => $c->creator?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'options' => [
                'senders' => SenderAccounts::options($request->user()),
                'templates' => EmailTemplate::query()->where('is_active', true)->orderBy('name')->get()->map(fn (EmailTemplate $t) => EmailTemplateController::present($t)),
                'counselors' => array_map(fn ($c) => ['value' => $c['value'], 'label' => $c['label']], app(CounselorDirectory::class)->options()),
                'leadStatuses' => LeadStatus::options(),
                'fileStatuses' => FileStatus::options(),
            ],
            'placeholders' => TemplateRenderer::PLACEHOLDERS,
            'maxRecipients' => self::MAX_RECIPIENTS,
        ]);
    }

    /** How many people the chosen audience reaches, before sending. */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('email.manage');

        return response()->json(['count' => $this->recipients($this->audience($request))->count()]);
    }

    public function store(Request $request, EmailSender $sender, TemplateRenderer $renderer): RedirectResponse
    {
        Gate::authorize('email.manage');

        $audience = $this->audience($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email_account_id' => ['nullable', 'integer'],
            'email_template_id' => ['nullable', 'integer', Rule::exists(EmailTemplate::class, 'id')],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $accountId = isset($data['email_account_id']) ? (int) $data['email_account_id'] : null;
        if (! SenderAccounts::allows($request->user(), $accountId)) {
            throw ValidationException::withMessages(['email_account_id' => __('You cannot send from this mailbox.')]);
        }

        $recipients = $this->recipients($audience);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['audience' => __('Nobody with an email address matches this audience.')]);
        }

        if ($recipients->count() > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages(['audience' => __('This audience has :count people. Narrow it to :max or fewer.', ['count' => $recipients->count(), 'max' => self::MAX_RECIPIENTS])]);
        }

        $user = $request->user();
        $signature = $user->emailSignature?->body;

        $campaign = DB::transaction(function () use ($data, $audience, $accountId, $recipients, $user, $sender, $renderer, $signature) {
            $campaign = EmailCampaign::create([
                'name' => $data['name'],
                'email_account_id' => $accountId,
                'email_template_id' => $data['email_template_id'] ?? null,
                'subject' => $data['subject'],
                'body' => $data['body'],
                'audience' => $audience,
                'total' => $recipients->count(),
                'created_by' => $user->id,
            ]);

            foreach ($recipients as $recipient) {
                $vars = $renderer->variables($recipient, $user);
                $sender->queue([
                    'email_account_id' => $accountId,
                    'email_campaign_id' => $campaign->id,
                    'email_template_id' => $data['email_template_id'] ?? null,
                    'sent_by' => $user->id,
                    'recipient_type' => $recipient->getMorphClass(),
                    'recipient_id' => $recipient->getKey(),
                    'to_email' => $vars['email'],
                    'to_name' => $vars['name'],
                    'subject' => $renderer->fill($data['subject'], $vars),
                    'body' => $renderer->toHtml($renderer->fill($data['body'], $vars), $signature),
                ]);
            }

            return $campaign;
        });

        activity('email')->causedBy($user)->performedOn($campaign)->withProperties(['recipients' => $campaign->total, 'audience' => $audience])->log('Bulk email queued');
        $this->toast(['type' => 'success', 'message' => __(':count emails queued.', ['count' => $campaign->total])]);

        return back();
    }

    /** @return array{type: string, counselor: int|null, status: string|null} */
    private function audience(Request $request): array
    {
        $type = (string) $request->input('audience.type');

        $data = $request->validate([
            'audience' => ['required', 'array'],
            'audience.type' => ['required', Rule::in(['students', 'leads'])],
            'audience.counselor' => ['nullable', 'integer'],
            'audience.status' => ['nullable', 'string', $type === 'leads' ? Rule::enum(LeadStatus::class) : Rule::enum(FileStatus::class)],
        ]);

        return [
            'type' => $data['audience']['type'],
            'counselor' => isset($data['audience']['counselor']) ? (int) $data['audience']['counselor'] : null,
            'status' => $data['audience']['status'] ?? null,
        ];
    }

    /**
     * One recipient per address: a student and a lead can share an email, and two leads often do.
     *
     * @param  array{type: string, counselor: int|null, status: string|null}  $audience
     * @return \Illuminate\Support\Collection<int, Model>
     */
    private function recipients(array $audience): \Illuminate\Support\Collection
    {
        /** @var Collection<int, Model> $rows */
        $rows = $audience['type'] === 'leads'
            ? Lead::query()
                ->whereNotNull('email')->where('email', '!=', '')
                ->whereNull('duplicate_of_id')
                ->when($audience['counselor'], fn (Builder $q, int $c) => $q->where('counselor_id', $c))
                ->when($audience['status'], fn (Builder $q, string $s) => $q->where('status', $s))
                ->orderBy('id')->get()
            : Student::query()
                ->with('user:id,name,email')
                ->whereHas('user', fn (Builder $q) => $q->whereNull('deleted_at'))
                ->when($audience['counselor'], fn (Builder $q, int $c) => $q->where('counselor_id', $c))
                ->when($audience['status'], fn (Builder $q, string $s) => $q->whereHas('files', fn (Builder $f) => $f->where('status', $s)))
                ->orderBy('id')->get();

        return $rows->unique(fn (Model $m) => strtolower((string) ($m instanceof Student ? $m->user->email : $m->getAttribute('email'))))->values();
    }

    /** @param array<string, mixed> $audience */
    private function describe(array $audience): string
    {
        $who = ($audience['type'] ?? 'students') === 'leads' ? __('Leads') : __('Students');
        $status = $audience['status'] ?? null;

        if ($status !== null) {
            $label = ($audience['type'] ?? '') === 'leads' ? LeadStatus::tryFrom($status)?->label() : FileStatus::tryFrom($status)?->label();
            $who .= ' · '.($label ?? $status);
        }

        return $who;
    }
}
