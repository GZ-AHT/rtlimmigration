<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\PortalMessaging;
use App\Actions\Leads\AssignLead;
use App\Actions\Leads\CaptureLead;
use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Notifications\ChatActivity;
use App\Support\Chat\ChatSettings;
use App\Support\Presenters\ChatPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §18 chat history, transcripts, offline messages, tags and assignment.
 * Mounted in the admin and counselor areas. Staff reply to live chats in
 * Tawk.to itself; these screens are our record of what was said.
 */
class ConversationController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request, ChatSettings $settings): Response
    {
        Gate::authorize('viewAny', ChatConversation::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ChatStatus::class)],
            'assignee' => ['nullable', 'string', 'max:20'],
            'tag' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $kind = ChatKind::from((string) $request->route('kind', 'chat'));

        $isNative = $kind->isNative();

        $conversations = ChatConversation::query()
            ->visibleTo($user)
            ->where('kind', $kind)
            ->when($isNative, fn (Builder $q) => $q->withUnreadFor($user, ChatMessage::MODERATED_SENDERS))
            ->with(['assignee:id,name', 'student:id,student_number,user_id,counselor_id', 'student.user:id,name'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('visitor_name', 'like', $like)->orWhere('visitor_email', 'like', $like)
                    ->orWhere('subject', 'like', $like)->orWhere('first_message', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['tag'] ?? null, fn (Builder $q, string $t) => $q->whereJsonContains('tags', $t))
            ->when($filters['assignee'] ?? null, fn (Builder $q, string $a) => match ($a) {
                'unassigned' => $q->whereNull('assigned_to'),
                'me' => $q->where('assigned_to', $user->id),
                default => $q->where('assigned_to', (int) $a),
            })
            ->when($isNative, fn (Builder $q) => $q->orderByDesc('last_message_at'))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ChatConversation $c) => [...ChatPresenter::row($c), 'url' => $this->areaRoute($request, 'chat.show', $c)]);

        $counts = ChatConversation::query()->visibleTo($user)->where('kind', $kind)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('chat/Index', [
            'kind' => $kind->value,
            'conversations' => $conversations,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'assignee' => $filters['assignee'] ?? '',
                'tag' => $filters['tag'] ?? '',
            ],
            'statusCounts' => collect(ChatStatus::cases())->mapWithKeys(fn (ChatStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'statuses' => array_map(fn (ChatStatus $s) => ['value' => $s->value, 'label' => $s->labelFor($kind)], ChatStatus::cases()),
            'assignees' => $user->can('chat.view-all') ? $this->assignees() : [],
            'connected' => $settings->enabled(),
            'receiving' => $settings->hasWebhookSecret(),
            'topics' => ChatConversation::TOPICS,
            'urls' => [
                'index' => $this->areaRoute($request, 'chat.index'),
                'offline' => $this->areaRoute($request, 'chat.offline'),
                'portal' => $this->areaRoute($request, 'chat.portal'),
                'guest' => $this->areaRoute($request, 'chat.guest'),
                'moderation' => $user->can('chat.moderate') && $this->areaHas($request, 'chat.moderation') ? $this->areaRoute($request, 'chat.moderation') : null,
                'settings' => $user->can('chat.manage') && $this->areaHas($request, 'chat.settings') ? $this->areaRoute($request, 'chat.settings') : null,
                'tawk' => ChatSettings::DASHBOARD_URL,
            ],
        ]);
    }

    public function show(Request $request, ChatConversation $conversation, PortalMessaging $messaging): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['assignee:id,name', 'student.user:id,name', 'lead:id,lead_number']);
        $user = $request->user();
        $thread = null;

        if ($conversation->isNative()) {
            $messages = $messaging->messages($conversation);
            $messaging->markRead($conversation, $user);
            $viewer = ChatPresenter::viewer(ChatMessage::STAFF, $user->can('chat.moderate'), $conversation->visitor_name);
            $thread = [
                'messages' => $messages->map(fn (ChatMessage $m) => ChatPresenter::message($m, fn (ChatMessageAttachment $a) => $this->areaRoute($request, 'chat.attachments.show', $a), $viewer))->values()->all(),
                'cursor' => (int) $conversation->last_seq,
                'has_more' => $messages->count() === PortalMessaging::PAGE,
            ];
        }

        return Inertia::render('chat/Show', [
            'conversation' => ChatPresenter::detail($conversation),
            'thread' => $thread,
            'composer' => ChatPresenter::composer(),
            'topics' => ChatConversation::TOPICS,
            'assignees' => $user->can('assign', $conversation) ? $this->assignees() : [],
            'can' => [
                'update' => $user->can('update', $conversation),
                'reply' => $conversation->isNative() && $user->can('reply', $conversation),
                'assign' => $user->can('assign', $conversation),
                'createLead' => $user->can('update', $conversation) && ! $conversation->isPortal() && $conversation->lead_id === null && $conversation->student_id === null,
            ],
            'maxTags' => ChatSettings::MAX_TAGS,
            'urls' => [
                'index' => $this->areaRoute($request, match ($conversation->kind) {
                    ChatKind::Offline => 'chat.offline',
                    ChatKind::Portal => 'chat.portal',
                    ChatKind::Guest => 'chat.guest',
                    default => 'chat.index',
                }),
                'messages' => $this->areaRoute($request, 'chat.messages.index', $conversation),
                'send' => $this->areaRoute($request, 'chat.messages.store', $conversation),
                'update' => $this->areaRoute($request, 'chat.update', $conversation),
                'lead' => $this->areaRoute($request, 'chat.lead', $conversation),
                'student' => $conversation->student && $user->can('view', $conversation->student) ? $this->areaRoute($request, 'students.show', $conversation->student) : null,
                'leadRecord' => $conversation->lead && $user->can('view', $conversation->lead) ? $this->areaRoute($request, 'leads.show', $conversation->lead) : null,
                'tawk' => ChatSettings::DASHBOARD_URL,
                'moderation' => $user->can('chat.moderate') && $this->areaHas($request, 'chat.moderation') ? $this->areaRoute($request, 'chat.moderation') : null,
            ],
        ]);
    }

    public function update(Request $request, ChatConversation $conversation, PortalMessaging $messaging): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(ChatStatus::class)],
            'tags' => ['sometimes', 'array', 'max:'.ChatSettings::MAX_TAGS],
            'tags.*' => ['string', 'max:40', 'distinct:ignore_case'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (array_key_exists('tags', $data)) {
            $conversation->tags = array_values(array_filter(array_map(fn (string $t) => trim($t), $data['tags']), fn (string $t) => $t !== ''));
        }

        if (isset($data['status'])) {
            $conversation->status = ChatStatus::from($data['status']);
        }

        $newAssignee = null;
        $previousAssignee = null;

        $target = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        if (array_key_exists('assigned_to', $data) && $target !== $conversation->assigned_to) {
            Gate::authorize('assign', $conversation);

            if ($target !== null) {
                $newAssignee = User::find($target);

                if (! $newAssignee || ! $newAssignee->isActive() || ! $newAssignee->can('chat.access')) {
                    throw ValidationException::withMessages(['assigned_to' => __('Choose an active staff member who can use live chat.')]);
                }
            }

            $previousAssignee = $conversation->assignee;
            $conversation->assigned_to = $target;
        }

        $conversation->save();

        // Portal threads and website chats keep a visible note of every hand-over.
        if ($conversation->isNative() && $conversation->wasChanged('assigned_to')) {
            $messaging->note($conversation, $newAssignee
                ? __('Conversation assigned to :to by :by.', ['to' => $newAssignee->name, 'by' => $request->user()->name])
                : __('Conversation unassigned from :from by :by.', ['from' => $previousAssignee->name ?? __('staff'), 'by' => $request->user()->name]));
        }

        if ($newAssignee && $newAssignee->isNot($request->user())) {
            $newAssignee->notify(new ChatActivity($conversation, 'assigned'));
        }

        $this->toast(['type' => 'success', 'message' => __('Conversation updated.')]);

        return back();
    }

    /**
     * REQ §18 workflow: website visitor → chat → counselor → student
     * conversion. Turns the visitor into a lead, from where the normal
     * lead-to-student conversion applies.
     */
    public function lead(Request $request, ChatConversation $conversation, CaptureLead $capture, AssignLead $assign): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        if ($conversation->lead_id !== null || $conversation->student_id !== null) {
            throw ValidationException::withMessages(['lead' => __('This conversation is already linked to a lead or a student.')]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
        ]);

        $user = $request->user();

        $lead = $capture([
            ...$data,
            'message' => $conversation->subject ? "{$conversation->subject}\n\n{$conversation->first_message}" : $conversation->first_message,
        ], LeadSource::LiveChat, $user);

        // A counselor only sees leads assigned to them, so keep theirs with them.
        if ($user->hasRole(Role::Counselor->value) && ! $user->can('leads.assign')) {
            $assign($lead, $user, $user, __('Created from a live chat.'));
        }

        $conversation->update(['lead_id' => $lead->id]);

        $this->toast(['type' => 'success', 'message' => __('Lead :number created.', ['number' => $lead->lead_number])]);

        return back();
    }

    /** @return list<array{value: string, label: string}> */
    private function assignees(): array
    {
        return array_values(User::query()
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (User $u) => $u->can('chat.access'))
            ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])
            ->all());
    }
}
