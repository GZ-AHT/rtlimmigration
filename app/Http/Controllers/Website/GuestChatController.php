<?php

namespace App\Http\Controllers\Website;

use App\Actions\Chat\PortalMessaging;
use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatGuest;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Support\Chat\AutoReply\AutoReplyService;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\Security\ChatActor;
use App\Support\Chat\Security\ChatGuard;
use App\Support\Chat\Security\ChatSecurityException;
use App\Support\Chat\Security\Turnstile;
use App\Support\Phone\PhoneNumbers;
use App\Support\Presenters\ChatPresenter;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Website chat for visitors without an account (REQ §11 F).
 *
 * A visitor is identified by a random 64-character token in an encrypted,
 * HTTP-only, SameSite cookie; only its hash is stored. Every request
 * resolves the conversation from that token, never from an ID in the URL,
 * so one visitor cannot reach another's chat. The IP address is security
 * metadata (limits, blocks), never the identity.
 *
 * Starting a chat is protected by a honeypot field, a signed form time
 * stamp (too-fast submissions), a bot User-Agent check, a per-IP limit on
 * new chats, and Cloudflare Turnstile when configured. Messages go through
 * the same pipeline as portal messages (ChatMessageController::send).
 */
class GuestChatController extends Controller
{
    /** A human needs at least this long to fill in the form. */
    private const MIN_FORM_SECONDS = 3;

    private const FORM_TTL_SECONDS = 7200;

    private const BOT_AGENTS = '~(curl|wget|python-requests|python-urllib|aiohttp|httpclient|okhttp|go-http-client|java/|libwww|scrapy|headless|phantomjs|selenium|puppeteer|playwright|\bbot\b|spider|crawler)~i';

    public function __construct(
        private ChatSecuritySettings $settings,
        private ChatGuard $guard,
        private PortalMessaging $messaging,
        private Turnstile $turnstile,
        private AutoReplyService $autoReply,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('website/Chat', $this->state($request));
    }

    /**
     * The same state as the chat page, as JSON, for the chat popup shown on
     * every website page. Still identified by the visitor's cookie only.
     */
    public function widget(Request $request): JsonResponse
    {
        return response()->json($this->state($request))->header('Cache-Control', 'no-store, private');
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $enabled = $this->settings->guestChatEnabled();
        $guest = $enabled ? $this->currentGuest($request) : null;
        $conversation = $guest?->conversation;
        $thread = null;

        if ($conversation !== null) {
            $messages = $this->messaging->messages($conversation);
            $thread = [
                'messages' => $messages->map(fn (ChatMessage $m) => ChatPresenter::message($m, self::attachmentUrl(), self::viewer($conversation)))->values()->all(),
                'cursor' => (int) $conversation->last_seq,
                'has_more' => $messages->count() === PortalMessaging::PAGE,
                'unread' => 0,
                'status' => $conversation->status->value,
            ];
        }

        $student = $request->user()?->student;

        return [
            'enabled' => $enabled,
            'visitor' => $guest ? ['name' => $guest->name, 'suspended' => $guest->isSuspended(), 'linked' => $guest->linked_user_id !== null] : null,
            'thread' => $thread,
            'composer' => ChatPresenter::composer(guest: true),
            // The country picker is only needed on the start form.
            'countries' => $thread === null && $enabled ? PhoneNumbers::countries() : [],
            'defaultCountry' => PhoneNumbers::DEFAULT_COUNTRY,
            'formToken' => Crypt::encryptString((string) time()),
            'captcha' => [
                'siteKey' => $this->settings->captchaAvailable() ? $this->settings->turnstileSiteKey() : null,
                'required' => $enabled && $this->guard->captchaRequired($request->ip(), $guest, starting: $guest === null),
            ],
            'canLink' => $student !== null && $guest !== null && $guest->linked_user_id === null,
            'portalChatUrl' => $student !== null ? route('portal.chat', absolute: false) : null,
        ];
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless($this->settings->guestChatEnabled(), 404);

        $ip = $request->ip();
        $actor = new ChatActor(null, null, $ip);
        $this->guard->ensureNotBlocked($actor);
        $this->checkHoneypot($request, $actor);

        $suspicion = $this->suspicion($request);

        if ($suspicion !== null) {
            $this->guard->record($actor, 'bot_suspected', 'warning', null, null, ['signal' => $suspicion]);
            $this->guard->markSuspicious($ip);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[^<>{}\[\]\\\\\/@=;]+$/u'],
            'phone' => ['required', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'body' => ['nullable', 'string', 'max:'.PortalMessaging::MAX_BODY],
            'captcha_token' => ['nullable', 'string', 'max:2048'],
            'client_id' => ['nullable', 'uuid'],
        ], [
            'name.regex' => __('Enter your name using letters only.'),
            'phone.required' => __('Enter your phone number.'),
        ]);

        $phone = $this->validPhone($data['phone'], (string) ($data['country'] ?? PhoneNumbers::DEFAULT_COUNTRY));

        $captchaPassed = false;

        if ($this->guard->captchaRequired($ip, null, starting: true)) {
            $this->verifyCaptcha($data['captcha_token'] ?? null, $actor);
            $captchaPassed = true;
        } elseif ($suspicion !== null) {
            // Without Turnstile there is no challenge to offer, so a request
            // that looks automated is refused (a person can simply retry).
            throw new ChatSecurityException(422, __('Please wait a few seconds, then try again.'));
        }

        $this->guard->admitGuestStart($ip, $actor);

        $token = Str::random(64);

        [$conversation, $guest] = DB::transaction(function () use ($data, $request, $token, $phone) {
            $conversation = ChatConversation::query()->create([
                'kind' => ChatKind::Guest,
                'external_id' => 'guest-'.Str::uuid(),
                'status' => ChatStatus::Open,
                'visitor_name' => trim($data['name']),
                'visitor_phone' => $phone,
                'started_at' => now(),
            ]);

            $guest = ChatGuest::query()->create([
                'chat_conversation_id' => $conversation->id,
                'token_hash' => ChatGuest::hashToken($token),
                'name' => trim($data['name']),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_seen_at' => now(),
            ]);

            return [$conversation, $guest];
        });

        if ($captchaPassed) {
            $this->guard->captchaPassed($guest);
        }

        // A chat starts from a name and a phone number alone; the assistant opens with a greeting.
        // A first message is still accepted (older pages and API clients send one).
        if (filled($data['body'] ?? null)) {
            try {
                $response = app(ChatMessageController::class)->send($request, $conversation, ChatMessage::VISITOR, ChatActor::guest($guest, $ip), self::viewer($conversation), self::attachmentUrl());
            } catch (\Throwable $e) {
                // No half-started chats: without its first message there is nothing to keep.
                $conversation->delete();

                throw $e;
            }

            $response = $this->withAutoReply($response, $conversation);
        } else {
            $response = response()->json(['message' => null]);
            $welcome = $this->autoReply->welcome($conversation);

            if ($welcome !== null) {
                $response->setData(['message' => null, 'auto_reply' => $this->presentReply($welcome, $conversation)]);
            }
        }

        return $response->setStatusCode(201)->withCookie($this->cookie($request, $token));
    }

    /** The phone number in E.164, or a validation error that names the country and gives an example. */
    private function validPhone(string $input, string $country): string
    {
        $country = PhoneNumbers::isCountry($country) ? strtoupper($country) : PhoneNumbers::DEFAULT_COUNTRY;
        $phone = PhoneNumbers::normalize($input, $country);

        if ($phone === null) {
            $name = \Locale::getDisplayRegion("-{$country}", 'en') ?: $country;
            $example = PhoneNumbers::example($country);

            throw ValidationException::withMessages(['phone' => $example
                ? __('Enter a valid :country phone number, for example :example.', ['country' => $name, 'example' => $example])
                : __('Enter a valid phone number.')]);
        }

        return $phone;
    }

    public function messages(Request $request, ChatMessageController $controller): JsonResponse
    {
        [$guest, $conversation] = $this->requireGuest($request);

        return $controller->poll($request, $conversation, self::viewer($conversation), self::attachmentUrl());
    }

    public function store(Request $request, ChatMessageController $controller): JsonResponse
    {
        [$guest, $conversation] = $this->requireGuest($request);
        $actor = ChatActor::guest($guest, $request->ip());

        $this->guard->ensureNotBlocked($actor, $conversation);
        $this->checkHoneypot($request, $actor);

        if ($this->guard->captchaRequired($request->ip(), $guest, starting: false)) {
            $this->verifyCaptcha($request->input('captcha_token'), $actor);
            $this->guard->captchaPassed($guest);
        }

        return $this->withAutoReply($controller->send($request, $conversation, ChatMessage::VISITOR, $actor, self::viewer($conversation), self::attachmentUrl()), $conversation);
    }

    /**
     * Adds the automatic reply to the answer for a message that was just stored, so the
     * page can show a typing indicator first and then the reply. Only for a new message:
     * a retry of one already stored (status 200) never gets a second reply.
     */
    private function withAutoReply(JsonResponse $response, ChatConversation $conversation): JsonResponse
    {
        if ($response->getStatusCode() !== 201) {
            return $response;
        }

        $data = $response->getData(true);
        $message = isset($data['message']['id']) ? ChatMessage::query()->find((int) $data['message']['id']) : null;
        $reply = $message ? $this->autoReply->respond($conversation, $message) : null;

        if ($reply === null) {
            return $response;
        }

        $data['auto_reply'] = $this->presentReply($reply, $conversation);

        return $response->setData($data);
    }

    /** @return array{message: array<string, mixed>, typing_ms: int, handover: bool} */
    private function presentReply(ChatMessage $reply, ChatConversation $conversation): array
    {
        // A person's welcome appears at once: no typing indicator for something nobody is typing.
        $human = $reply->sender_type === ChatMessage::STAFF;

        return [
            'message' => ChatPresenter::message($reply, self::attachmentUrl(), self::viewer($conversation)),
            'typing_ms' => $human ? 0 : AutoReplyService::typingMs((string) $reply->body),
            'handover' => $human,
        ];
    }

    public function attachment(Request $request, ChatMessageAttachment $attachment, ChatMessageController $controller): StreamedResponse
    {
        [, $conversation] = $this->requireGuest($request);

        // Only files in the visitor's own chat; anything else looks missing.
        abort_unless($attachment->message->chat_conversation_id === $conversation->id, 404);

        return $controller->download($request, $attachment, self::viewer($conversation));
    }

    /**
     * Links the visitor's chat to the student account they have just
     * signed in to. Proof is holding both: the chat's cookie and the
     * account's session. Nothing is linked by matching an email address.
     */
    public function link(Request $request): RedirectResponse
    {
        $user = $request->user();
        $student = $user->student;
        abort_if($student === null, 403);

        $guest = $this->currentGuest($request);
        abort_if($guest === null || $guest->conversation === null, 404);

        if ($guest->linked_user_id !== null) {
            abort_if($guest->linked_user_id !== $user->id, 409);

            return redirect()->route('portal.chat');
        }

        DB::transaction(function () use ($guest, $student, $user) {
            $guest->forceFill(['linked_user_id' => $user->id, 'linked_at' => now()])->save();
            $guest->conversation->forceFill(['student_id' => $student->id, 'assigned_to' => $guest->conversation->assigned_to ?? $student->counselor_id])->save();
        });

        $this->messaging->note($guest->conversation, __('Linked to the student account of :name.', ['name' => $user->name]));

        activity('chat-security')->causedBy($user)->performedOn($guest->conversation)->log('Website chat linked to a student account');

        $this->toast(['type' => 'success', 'message' => __('Your website chat is now part of your account. Your counselor can see it.')]);

        return redirect()->route('portal.chat')->withCookie(Cookie::forget(ChatGuest::COOKIE));
    }

    /** The visitor behind this request's cookie, if the token is valid and recent. */
    private function currentGuest(Request $request): ?ChatGuest
    {
        $token = $request->cookie(ChatGuest::COOKIE);

        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $guest = ChatGuest::query()
            ->where('token_hash', ChatGuest::hashToken($token))
            ->where('last_seen_at', '>=', now()->subDays($this->settings->int('guest_session_days')))
            ->with('conversation')
            ->first();

        // Writes at most every ten minutes, so polling stays read-only.
        if ($guest !== null && ($guest->last_seen_at === null || $guest->last_seen_at->lt(now()->subMinutes(10)))) {
            $guest->forceFill(['last_seen_at' => now()])->save();
        }

        return $guest;
    }

    /** @return array{0: ChatGuest, 1: ChatConversation} */
    private function requireGuest(Request $request): array
    {
        abort_unless($this->settings->guestChatEnabled(), 404);

        $guest = $this->currentGuest($request);

        if ($guest === null || $guest->conversation === null) {
            throw new ChatSecurityException(403, __('Your chat session has ended. Reload the page to start a new chat.'));
        }

        return [$guest, $guest->conversation];
    }

    /** Real visitors never see or fill the "website" field. */
    private function checkHoneypot(Request $request, ChatActor $actor): void
    {
        if ($request->filled('website')) {
            $this->guard->record($actor, 'honeypot', 'warning');
            $this->guard->markSuspicious($actor->ip, $actor->guest);

            throw new ChatSecurityException(422, __('We could not send this. Please reload the page and try again.'));
        }
    }

    /** Why a start request looks automated, or null. */
    private function suspicion(Request $request): ?string
    {
        try {
            $issued = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (DecryptException) {
            throw new ChatSecurityException(422, __('This page has expired. Reload it and try again.'));
        }

        $age = time() - $issued;

        if ($age > self::FORM_TTL_SECONDS || $age < 0) {
            throw new ChatSecurityException(422, __('This page has expired. Reload it and try again.'));
        }

        $agent = (string) $request->userAgent();

        return match (true) {
            $age < self::MIN_FORM_SECONDS => 'too_fast',
            $agent === '' || preg_match(self::BOT_AGENTS, $agent) === 1 => 'user_agent',
            default => null,
        };
    }

    private function verifyCaptcha(mixed $token, ChatActor $actor): void
    {
        if (! is_string($token) || $token === '') {
            throw new ChatSecurityException(422, __('Please complete the security check.'), captchaRequired: true);
        }

        if (! $this->turnstile->verify($token, $actor->ip)) {
            $this->guard->record($actor, 'captcha_failed', 'warning');

            throw new ChatSecurityException(422, __('The security check did not pass. Please try again.'), captchaRequired: true);
        }
    }

    private function cookie(Request $request, string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(ChatGuest::COOKIE, $token, $this->settings->int('guest_session_days') * 1440, '/', null, $request->isSecure(), true, false, 'lax');
    }

    /** @return array{side: string, moderator: bool, visitor_name: string|null} */
    private static function viewer(ChatConversation $conversation): array
    {
        return ChatPresenter::viewer(ChatMessage::VISITOR, false, $conversation->visitor_name);
    }

    /** @return Closure(ChatMessageAttachment): string */
    private static function attachmentUrl(): Closure
    {
        return fn (ChatMessageAttachment $a) => route('chat.guest.attachments.show', $a, absolute: false);
    }
}
