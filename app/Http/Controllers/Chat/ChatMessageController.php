<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\PortalMessaging;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\ChatTyping;
use App\Support\Chat\Security\AttachmentInspector;
use App\Support\Chat\Security\AttachmentRejected;
use App\Support\Chat\Security\ChatActor;
use App\Support\Chat\Security\ChatGuard;
use App\Support\Chat\Security\ContentInspector;
use App\Support\Chat\Security\InspectedAttachment;
use App\Support\Chat\Security\Verdict;
use App\Support\Presenters\ChatPresenter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Message threads kept in this system (portal threads and website chats),
 * for staff (admin and counselor areas), the student (PortalChatController)
 * and website visitors (GuestChatController). JSON only: the page polls
 * index() with ?after= every few seconds and posts with store().
 *
 * Every message goes through the same checks on the server: blocks and
 * rate limits (ChatGuard), validation, duplicate detection, file checks
 * (AttachmentInspector) and, for students and visitors, the content checks
 * and moderation (ContentInspector).
 */
class ChatMessageController extends Controller
{
    use ResolvesAreaRoutes;

    public function __construct(
        private PortalMessaging $messaging,
        private ChatGuard $guard,
        private ChatSecuritySettings $settings,
        private ContentInspector $content,
        private AttachmentInspector $attachments,
    ) {}

    public function index(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->isNative(), 404);
        $conversation->loadMissing('student:id,user_id,counselor_id');
        Gate::authorize('view', $conversation);

        return $this->poll($request, $conversation, $this->staffViewer($request, $conversation), $this->staffAttachmentUrl($request));
    }

    public function store(Request $request, ChatConversation $conversation): JsonResponse
    {
        // Checked here as well as in the policy: Super Admin skips policies
        // (Gate::before), and Tawk.to chats must never get replies from here.
        abort_unless($conversation->isNative(), 404);
        $conversation->loadMissing('student:id,user_id,counselor_id');
        Gate::authorize('reply', $conversation);

        return $this->send(
            $request,
            $conversation,
            ChatMessage::STAFF,
            ChatActor::user($request->user(), $request->ip(), staff: true),
            $this->staffViewer($request, $conversation),
            $this->staffAttachmentUrl($request),
        );
    }

    /** Called while a team member types a reply, so the visitor sees "… is typing". */
    public function typing(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->isNative(), 404);
        $conversation->loadMissing('student:id,user_id,counselor_id');
        Gate::authorize('reply', $conversation);

        ChatTyping::agentTyping($conversation, $request->user());

        return response()->json(['ok' => true]);
    }

    public function attachment(Request $request, ChatMessageAttachment $attachment): StreamedResponse
    {
        $conversation = $attachment->message->conversation;
        $conversation->loadMissing('student:id,user_id,counselor_id');
        Gate::authorize('view', $conversation);

        return $this->download($request, $attachment, $this->staffViewer($request, $conversation));
    }

    /**
     * Streams a file to someone already allowed to see the thread. Files of
     * a held message are only for its sender and moderators.
     *
     * @param  array{side: string, moderator: bool, visitor_name: string|null}  $viewer
     */
    public function download(Request $request, ChatMessageAttachment $attachment, array $viewer): StreamedResponse
    {
        $message = $attachment->message;
        abort_if($message->isHeld() && $message->sender_type !== $viewer['side'] && ! $viewer['moderator'], 403);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $inline = ! $request->boolean('download') && in_array($attachment->mime_type, config('documents.inline'), true);

        return $disk->response($attachment->path, AttachmentInspector::displayName($attachment->original_name, pathinfo($attachment->path, PATHINFO_EXTENSION)), [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    /**
     * Changes since ?after= (a sequence number: new messages and moderation
     * updates), or earlier messages before ?before= (a message ID). A poll
     * with nothing new is answered from the conversation row alone.
     *
     * @param  array{side: string, moderator: bool, visitor_name: string|null}  $viewer
     * @param  Closure(ChatMessageAttachment): string  $attachmentUrl
     */
    public function poll(Request $request, ChatConversation $conversation, array $viewer, Closure $attachmentUrl): JsonResponse
    {
        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:1'],
        ]);

        $after = isset($data['after']) ? (int) $data['after'] : null;
        $cursor = (int) $conversation->last_seq;
        $meta = ['cursor' => $cursor, 'status' => $conversation->status->value];

        // Visitors and students see when a team member is typing; staff do not need to.
        if ($viewer['side'] !== ChatMessage::STAFF) {
            $meta['typing'] = ChatTyping::current($conversation);
        }

        if ($after !== null && $after >= $cursor) {
            return response()->json(['messages' => [], ...$meta]);
        }

        $messages = $this->messaging->messages($conversation, $after, isset($data['before']) ? (int) $data['before'] : null);

        if ($after !== null && $messages->isNotEmpty() && $request->user() !== null) {
            $this->messaging->markRead($conversation, $request->user(), (int) $messages->max('id'));
        }

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $m) => ChatPresenter::message($m, $attachmentUrl, $viewer))->all(),
            'has_more' => $after === null && $messages->count() === PortalMessaging::PAGE,
            ...$meta,
        ]);
    }

    /**
     * @param  array{side: string, moderator: bool, visitor_name: string|null}  $viewer
     * @param  Closure(ChatMessageAttachment): string  $attachmentUrl
     */
    public function send(Request $request, ChatConversation $conversation, string $senderType, ChatActor $actor, array $viewer, Closure $attachmentUrl): JsonResponse
    {
        $present = fn (ChatMessage $m, int $status) => response()->json(['message' => ChatPresenter::message($m, $attachmentUrl, $viewer)], $status);

        // A retry of a message already stored is answered first, so it never
        // counts against the limits.
        $clientId = $request->input('client_id');

        if (is_string($clientId) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientId) === 1
            && ($existing = $this->messaging->findByClientId($conversation, $clientId))) {
            return $present($existing, 200);
        }

        $this->guard->admit($actor, $conversation);

        $data = $this->validateMessage($request, $actor);
        $body = self::cleanBody($data['body'] ?? null);
        $files = $data['files'] ?? [];

        if ($body === null && $files === []) {
            throw ValidationException::withMessages(['body' => __('Write a message or attach a file.')]);
        }

        $this->guard->rejectDuplicate($actor, $conversation, $body);

        $inspected = $this->inspectFiles($files, $actor, $conversation);
        $verdict = $this->judge($actor, $senderType, $body);

        $message = $this->messaging->post($conversation, $actor->user, $senderType, $body, $inspected, $data['client_id'] ?? null, $verdict);

        if ($senderType === ChatMessage::STAFF) {
            ChatTyping::clear($conversation);
        }

        if ($message->wasRecentlyCreated && $verdict->status !== ModerationStatus::Approved) {
            $this->reportVerdict($actor, $conversation, $message, $verdict);
        }

        return $present($message, $message->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Text is kept as written, minus control characters and invisible
     * direction overrides. It is always shown as plain text, never HTML.
     */
    public static function cleanBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = (string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]~u', '', $body);
        $body = trim($body);

        return $body === '' ? null : $body;
    }

    /** @return array{body?: string|null, files?: list<UploadedFile>, client_id?: string|null} */
    private function validateMessage(Request $request, ChatActor $actor): array
    {
        $allowed = $this->settings->allowedExtensions();
        $filesAllowed = $allowed !== [] && $actor->guest === null;
        $maxFiles = $this->settings->int('max_files');
        $maxKb = $this->settings->int('max_attachment_kb');

        $fileRules = $filesAllowed
            ? ['nullable', 'array', 'max:'.$maxFiles]
            : ['prohibited'];

        /** @var array{body?: string|null, files?: list<UploadedFile>, client_id?: string|null} */
        return $request->validate([
            'body' => ['nullable', 'string', 'max:'.PortalMessaging::MAX_BODY],
            'files' => $fileRules,
            'files.*' => $filesAllowed ? [
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', array_keys($allowed)),
                'mimetypes:'.implode(',', array_unique(array_merge(...array_values($allowed)))),
            ] : ['prohibited'],
            'client_id' => ['nullable', 'uuid'],
        ], [
            'files.prohibited' => __('Attachments are switched off in chat. Send text only.'),
            'files.*.prohibited' => __('Attachments are switched off in chat. Send text only.'),
            'files.max' => __('Attach up to :max files at a time.', ['max' => $maxFiles]),
            'files.*.mimes' => __('This type of file cannot be sent in chat.'),
            'files.*.mimetypes' => __('This type of file cannot be sent in chat.'),
            'files.*.max' => __('Each file must be :max MB or smaller.', ['max' => round($maxKb / 1024, 1)]),
        ]);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<InspectedAttachment>
     */
    private function inspectFiles(array $files, ChatActor $actor, ChatConversation $conversation): array
    {
        $inspected = [];

        foreach ($files as $i => $file) {
            try {
                $inspected[] = $this->attachments->inspect($file);
            } catch (AttachmentRejected $e) {
                $this->guard->record($actor, $e->malicious ? 'malicious_attachment' : 'invalid_attachment', $e->malicious ? 'critical' : 'info', $conversation, null, [
                    'reason' => $e->reason,
                    // The visitor's own file name, cleaned, is useful for review; its contents are never kept.
                    'file' => AttachmentInspector::displayName($file->getClientOriginalName(), strtolower($file->getClientOriginalExtension()) ?: 'bin'),
                    'size' => (int) $file->getSize(),
                ]);

                if ($e->malicious) {
                    $this->guard->markSuspicious($actor->guest ? $actor->ip : null, $actor->guest);
                    $this->guard->alert('malicious_attachment', $actor->key(), __('Unsafe chat attachment refused'), __(':who tried to send a file that failed the safety checks (:reason).', ['who' => $this->guard->describe($actor), 'reason' => $e->reason]));
                }

                throw ValidationException::withMessages(["files.{$i}" => $e->getMessage()]);
            }
        }

        return $inspected;
    }

    /** Content checks for students and visitors; staff messages are not moderated. */
    private function judge(ChatActor $actor, string $senderType, ?string $body): Verdict
    {
        if (! in_array($senderType, ChatMessage::MODERATED_SENDERS, true) || $body === null) {
            return Verdict::approved();
        }

        $verdict = $this->content->inspect($body);

        if ($this->guard->repeatCount($actor, $body) >= $this->guard->repeatThreshold()) {
            $verdict = $verdict->with(ModerationStatus::Blocked, 'repeated_message');
        }

        return $verdict;
    }

    /** Logs a non-approved message and alerts Super Admin when it matters. */
    private function reportVerdict(ChatActor $actor, ChatConversation $conversation, ChatMessage $message, Verdict $verdict): void
    {
        $blocked = $verdict->status === ModerationStatus::Blocked;
        $this->guard->record($actor, $blocked ? 'content_blocked' : 'content_flagged', $blocked ? 'warning' : 'info', $conversation, $message, [
            'status' => $verdict->status->value,
            'reasons' => $verdict->reasons,
        ]);

        $who = $this->guard->describe($actor);

        if ($verdict->suspiciousUrls !== []) {
            $this->guard->record($actor, 'suspicious_link', 'warning', $conversation, $message, ['hosts' => array_values(array_unique(array_filter(array_map(fn (string $u) => parse_url(str_contains($u, '://') ? $u : "http://{$u}", PHP_URL_HOST), $verdict->suspiciousUrls))))]);
            $this->guard->alert('suspicious_link', $actor->key(), __('Suspicious link in chat'), __(':who sent a message with a suspicious link. It is waiting in the moderation queue.', ['who' => $who]), 'moderation');
        }

        if (in_array('repeated_message', $verdict->reasons, true)) {
            $this->guard->record($actor, 'repeated_spam', 'warning', $conversation, $message);
            $this->guard->alert('repeated_spam', $actor->key(), __('Repeated spam in chat'), __(':who keeps sending the same message. The copies are held for review.', ['who' => $who]), 'moderation');
        } elseif ($blocked || $verdict->status === ModerationStatus::Pending) {
            $this->guard->alert('held_message', $actor->key(), __('Chat message held for review'), __('A message from :who was held by the safety checks (:reasons).', ['who' => $who, 'reasons' => implode(', ', ChatPresenter::reasonLabels($verdict->reasons))]), 'moderation');
        }

        if ($actor->guest !== null && ($blocked || $verdict->status === ModerationStatus::Pending)) {
            $this->guard->markSuspicious($actor->ip, $actor->guest);
        }
    }

    /** @return array{side: string, moderator: bool, visitor_name: string|null} */
    private function staffViewer(Request $request, ChatConversation $conversation): array
    {
        return ChatPresenter::viewer(ChatMessage::STAFF, $request->user()->can('chat.moderate'), $conversation->visitor_name);
    }

    /** @return Closure(ChatMessageAttachment): string */
    private function staffAttachmentUrl(Request $request): Closure
    {
        return fn (ChatMessageAttachment $a) => $this->areaRoute($request, 'chat.attachments.show', $a);
    }
}
