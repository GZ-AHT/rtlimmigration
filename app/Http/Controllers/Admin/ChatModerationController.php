<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Chat\ModerateMessage;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatModerationEvent;
use App\Support\Presenters\ChatPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §11 C: the moderation queue. Moderators (chat.moderate; Super Admin
 * always) review flagged, pending and blocked chat messages and approve or
 * reject them with a reason. Nothing is deleted.
 */
class ChatModerationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('chat.moderate');

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queue', ...array_column(ModerationStatus::cases(), 'value')])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'queue';

        $messages = ChatMessage::query()
            ->with(['conversation:id,kind,visitor_name,student_id', 'conversation.student:id,student_number', 'user:id,name', 'attachments', 'reviewer:id,name'])
            ->when($status === 'queue',
                fn (Builder $q) => $q->whereIn('moderation_status', ModerationStatus::queueValues()),
                fn (Builder $q) => $q->where('moderation_status', $status))
            // Approved messages only appear here once a moderator has looked at them.
            ->when($status === ModerationStatus::Approved->value, fn (Builder $q) => $q->whereNotNull('reviewed_at'))
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where('body', 'like', $like);
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $history = ChatModerationEvent::query()
            ->whereIn('chat_message_id', $messages->getCollection()->pluck('id'))
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->groupBy('chat_message_id');

        $viewer = ChatPresenter::viewer('moderator', true);
        $canSecurity = $request->user()->can('security.manage');

        $messages->through(fn (ChatMessage $m) => [
            ...ChatPresenter::message($m, fn (ChatMessageAttachment $a) => route('admin.chat.attachments.show', $a, absolute: false), [...$viewer, 'visitor_name' => $m->conversation->visitor_name]),
            'status' => $m->moderation_status->value,
            'status_label' => $m->moderation_status->label(),
            'status_tone' => $m->moderation_status->tone(),
            'reasons' => ChatPresenter::reasonLabels($m->moderation_reasons ?? []),
            'conversation' => [
                'id' => $m->chat_conversation_id,
                'kind_label' => $m->conversation->kind->label(),
                'student_number' => $m->conversation->student?->student_number,
                'url' => route('admin.chat.show', $m->chat_conversation_id, absolute: false),
            ],
            'reviewed_by' => $m->reviewer?->name,
            'reviewed_at' => $m->reviewed_at?->toIso8601String(),
            'history' => ($history[$m->id] ?? collect())->map(fn (ChatModerationEvent $e) => [
                'id' => $e->id,
                'from' => $e->from_status?->label(),
                'to' => $e->to_status->label(),
                'note' => $e->note,
                'by' => $e->user_id === null ? __('Automatic checks') : ($e->user->name ?? __('Former user')),
                'at' => $e->created_at?->toIso8601String(),
            ])->values(),
            'decide_url' => route('admin.chat.moderation.decide', $m, absolute: false),
        ]);

        $counts = ChatMessage::query()->selectRaw('moderation_status, count(*) as c')->groupBy('moderation_status')->pluck('c', 'moderation_status');

        return Inertia::render('admin/chat/Moderation', [
            'messages' => $messages,
            'filters' => ['status' => $status, 'search' => $filters['search'] ?? ''],
            'counts' => [
                'queue' => (int) collect(ModerationStatus::queueValues())->sum(fn (string $s) => $counts[$s] ?? 0),
                ...collect(ModerationStatus::cases())->mapWithKeys(fn (ModerationStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            ],
            'statuses' => ModerationStatus::options(),
            'securityUrl' => $canSecurity ? route('admin.chat.security.activity', absolute: false) : null,
        ]);
    }

    public function decide(Request $request, ChatMessage $message, ModerateMessage $moderate): RedirectResponse
    {
        Gate::authorize('chat.moderate');
        abort_if($message->sender_type === ChatMessage::SYSTEM, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in([ModerationStatus::Approved->value, ModerationStatus::Rejected->value])],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:500'],
        ], [
            'note.required_if' => __('Give a reason for rejecting the message.'),
        ]);

        $moderate($message, ModerationStatus::from($data['decision']), $request->user(), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => $data['decision'] === 'approved' ? __('Message approved.') : __('Message rejected.')]);

        return back();
    }
}
