<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailMessageStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendEmailMessage;
use App\Models\EmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailMessage;
use App\Support\Presenters\EmailPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §3 Email Delivery Logs and Email Tracking. */
class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('email.manage');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(EmailMessageStatus::class)],
            'account' => ['nullable', 'integer'],
            'campaign' => ['nullable', 'integer'],
        ]);

        $messages = EmailMessage::query()
            ->with(['sender:id,name', 'campaign:id,name', 'recipient'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('to_email', 'like', $like)->orWhere('to_name', 'like', $like)->orWhere('subject', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['account'] ?? null, fn (Builder $q, int|string $a) => $q->where('email_account_id', $a))
            ->when($filters['campaign'] ?? null, fn (Builder $q, int|string $c) => $q->where('email_campaign_id', $c))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EmailMessage $m) => EmailPresenter::message($m));

        $counts = EmailMessage::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/email/Logs', [
            'messages' => $messages,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'account' => isset($filters['account']) ? (string) $filters['account'] : '',
                'campaign' => isset($filters['campaign']) ? (string) $filters['campaign'] : '',
            ],
            'statusCounts' => $counts,
            'options' => [
                'statuses' => EmailMessageStatus::options(),
                'accounts' => EmailAccount::query()->orderBy('email')->get(['id', 'email'])->map(fn ($a) => ['value' => (string) $a->id, 'label' => $a->email])->all(),
                'campaigns' => EmailCampaign::query()->latest('id')->limit(50)->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
            ],
        ]);
    }

    /** Full message for the log's preview panel. The body is HTML this system generated from escaped text. */
    public function show(EmailMessage $message): JsonResponse
    {
        Gate::authorize('email.manage');

        return response()->json([...EmailPresenter::message($message->load(['sender:id,name', 'campaign:id,name', 'recipient'])), 'body' => $message->body]);
    }

    public function retry(Request $request, EmailMessage $message): RedirectResponse
    {
        Gate::authorize('email.manage');

        if ($message->status !== EmailMessageStatus::Failed) {
            throw ValidationException::withMessages(['message' => __('Only failed emails can be sent again.')]);
        }

        $message->update(['status' => EmailMessageStatus::Queued, 'error' => null]);

        if ($message->email_campaign_id !== null) {
            EmailCampaign::whereKey($message->email_campaign_id)->where('failed', '>', 0)->decrement('failed');
        }

        SendEmailMessage::dispatch($message->id);

        $this->toast(['type' => 'success', 'message' => __('Email queued again.')]);

        return back();
    }
}
