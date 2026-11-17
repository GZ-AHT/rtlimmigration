<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Chat\PortalMessaging;
use App\Enums\ChatKind;
use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Support\Chat\ChatSettings;
use App\Support\Chat\Security\ChatActor;
use App\Support\Presenters\ChatPresenter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REQ §10.1 "Live Chat: contact counselor / support", §18 "User Dashboard
 * Chat": the student's message thread with staff (kept here) and the
 * Tawk.to widget for instant chat, with past Tawk.to chats listed below.
 */
class PortalChatController extends Controller
{
    public function __construct(private PortalMessaging $messaging) {}

    public function __invoke(Request $request, ChatSettings $settings): Response
    {
        $user = $request->user();
        $student = $user->student;
        $thread = $student?->id ? ChatConversation::query()
            ->where('kind', ChatKind::Portal)
            ->where('external_id', ChatConversation::portalKey($student->id))
            ->withUnreadFor($user, ChatMessage::STAFF)
            ->first() : null;

        $messages = $thread ? $this->messaging->messages($thread) : collect();
        $unread = (int) ($thread->unread_count ?? 0);

        if ($thread) {
            $this->messaging->markRead($thread, $user);
        }

        return Inertia::render('portal/Chat', [
            'available' => $settings->widgetFor($user) !== null,
            'counselor' => $student?->counselor?->name,
            'thread' => $student === null ? null : [
                'messages' => $messages->map(fn (ChatMessage $m) => ChatPresenter::message($m, self::attachmentUrl(), self::viewer()))->values()->all(),
                'cursor' => (int) ($thread->last_seq ?? 0),
                'has_more' => $messages->count() === PortalMessaging::PAGE,
                'unread' => $unread,
                'status' => $thread?->status->value,
            ],
            'composer' => ChatPresenter::composer(),
            'history' => $student === null ? [] : ChatConversation::query()
                ->where('student_id', $student->id)
                ->where('kind', '!=', ChatKind::Portal->value)
                ->orderByDesc('started_at')->orderByDesc('id')
                ->limit(20)->get()
                ->map(fn (ChatConversation $c) => [
                    'id' => $c->id,
                    'kind_label' => $c->kind->label(),
                    'kind_icon' => $c->kind->icon(),
                    'status_label' => $c->status->labelFor($c->kind),
                    'status_tone' => $c->status->toneFor($c->kind),
                    'preview' => $c->first_message ? (string) str($c->first_message)->squish()->limit(120) : null,
                    'message_count' => $c->message_count,
                    'started_at' => $c->started_at?->toIso8601String(),
                ]),
        ]);
    }

    /** Polling and earlier history for the student's own thread. */
    public function messages(Request $request, ChatMessageController $controller): JsonResponse
    {
        $thread = $this->ownThread($request, create: false);

        if ($thread === null) {
            return response()->json(['messages' => [], 'cursor' => 0, 'status' => null]);
        }

        Gate::authorize('view', $thread);

        return $controller->poll($request, $thread, self::viewer(), self::attachmentUrl());
    }

    public function store(Request $request, ChatMessageController $controller): JsonResponse
    {
        $thread = $this->ownThread($request, create: true);
        abort_if($thread === null, 403);
        Gate::authorize('reply', $thread);

        return $controller->send($request, $thread, ChatMessage::STUDENT, ChatActor::user($request->user(), $request->ip(), staff: false), self::viewer(), self::attachmentUrl());
    }

    public function attachment(Request $request, ChatMessageAttachment $attachment, ChatMessageController $controller): StreamedResponse
    {
        $conversation = $attachment->message->conversation;
        $conversation->loadMissing('student:id,user_id,counselor_id');
        Gate::authorize('view', $conversation);

        return $controller->download($request, $attachment, self::viewer());
    }

    /** @return array{side: string, moderator: bool, visitor_name: string|null} */
    private static function viewer(): array
    {
        return ChatPresenter::viewer(ChatMessage::STUDENT);
    }

    /** @return Closure(ChatMessageAttachment): string */
    private static function attachmentUrl(): Closure
    {
        return fn (ChatMessageAttachment $a) => route('portal.chat.attachments.show', $a, absolute: false);
    }

    private function ownThread(Request $request, bool $create): ?ChatConversation
    {
        $student = $request->user()->student;

        if ($student === null) {
            return null;
        }

        $thread = $create
            ? $this->messaging->threadFor($student)
            : ChatConversation::query()->where('kind', ChatKind::Portal)->where('external_id', ChatConversation::portalKey($student->id))->first();

        $thread?->setRelation('student', $student);

        return $thread;
    }
}
