<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\ChatGuest;
use App\Models\ChatIpBlock;
use App\Models\ChatMessage;
use App\Models\ChatSecurityEvent;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\Security\ChatActor;
use App\Support\Chat\Security\ChatGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §11 G: Super Admin security controls for the chat. Everything here
 * needs security.manage, which only Super Admin has by default; Lead
 * Managers and Counselors cannot reach it. IP addresses are only ever
 * shown on these screens.
 */
class ChatSecurityController extends Controller
{
    public function edit(ChatSecuritySettings $settings): Response
    {
        Gate::authorize('security.manage');

        $since = now()->subDays(7);

        return Inertia::render('admin/chat/Security', [
            'settings' => $settings->forAdmin(),
            'limits' => collect(ChatSecuritySettings::LIMITS)->map(fn (array $l) => ['default' => $l[0], 'min' => $l[1], 'max' => $l[2]]),
            'attachmentTypes' => collect(ChatSecuritySettings::ATTACHMENT_TYPES)->map(fn (array $t, string $key) => ['value' => $key, 'label' => $t['label']])->values(),
            'captchaModes' => [
                ['value' => 'off', 'label' => 'Off'],
                ['value' => 'suspicious', 'label' => 'Only for suspicious visitors'],
                ['value' => 'always', 'label' => 'Every new website chat'],
            ],
            'malwareScanner' => config('chat.malware.driver') === 'clamav' ? 'ClamAV' : null,
            'stats' => [
                'queue' => ChatMessage::query()->whereIn('moderation_status', ModerationStatus::queueValues())->count(),
                'moderation' => collect(ModerationStatus::cases())->mapWithKeys(fn (ModerationStatus $s) => [$s->value => ChatMessage::query()->where('moderation_status', $s->value)->where('created_at', '>=', $since)->count()]),
                'events' => ChatSecurityEvent::query()->where('created_at', '>=', $since)->selectRaw('type, count(*) as c')->groupBy('type')->orderByDesc('c')->pluck('c', 'type')
                    ->map(fn ($c, $type) => ['type' => $type, 'label' => ChatSecurityEvent::TYPES[$type] ?? $type, 'count' => (int) $c])->values(),
                'blocks' => ChatIpBlock::query()->active()->count(),
                'suspended' => ChatGuest::query()->where('suspended_until', '>', now())->count(),
            ],
            'urls' => [
                'moderation' => route('admin.chat.moderation', absolute: false),
                'activity' => route('admin.chat.security.activity', absolute: false),
            ],
        ]);
    }

    public function update(Request $request, ChatSecuritySettings $settings): RedirectResponse
    {
        Gate::authorize('security.manage');

        $limitRules = [];

        foreach (ChatSecuritySettings::LIMITS as $key => [, $min, $max]) {
            $limitRules["limits.{$key}"] = ['required', 'integer', "min:{$min}", "max:{$max}"];
        }

        $data = $request->validate([
            'guest_chat_enabled' => ['required', 'boolean'],
            'limits' => ['required', 'array'],
            ...$limitRules,
            'attachment_types' => ['present', 'array'],
            'attachment_types.*' => ['string', Rule::in(array_keys(ChatSecuritySettings::ATTACHMENT_TYPES))],
            'blocked_keywords' => ['present', 'array', 'max:'.ChatSecuritySettings::MAX_LIST],
            'blocked_keywords.*' => ['string', 'max:120'],
            'blocked_domains' => ['present', 'array', 'max:'.ChatSecuritySettings::MAX_LIST],
            'blocked_domains.*' => ['string', 'max:120', 'regex:/^[a-z0-9*.\-:\/]+$/i'],
            'trusted_domains' => ['present', 'array', 'max:'.ChatSecuritySettings::MAX_LIST],
            'trusted_domains.*' => ['string', 'max:120', 'regex:/^[a-z0-9*.\-:\/]+$/i'],
            'captcha_mode' => ['required', Rule::in(ChatSecuritySettings::CAPTCHA_MODES)],
            'turnstile_site_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'turnstile_secret' => ['nullable', 'string', 'max:255'],
            'clear_turnstile_secret' => ['boolean'],
        ], [
            'blocked_domains.*.regex' => __('Enter domains like example.com, one per line.'),
            'trusted_domains.*.regex' => __('Enter domains like example.com, one per line.'),
            'turnstile_site_key.regex' => __('The site key is the code from your Cloudflare Turnstile widget.'),
        ]);

        $before = $settings->forAdmin();
        $settings->save($data);
        $after = $settings->forAdmin();

        activity('chat-security')->causedBy($request->user())->withProperties([
            'changed' => array_keys(array_filter($after, fn ($v, $k) => $v != ($before[$k] ?? null), ARRAY_FILTER_USE_BOTH)),
            'turnstile_secret_changed' => filled($data['turnstile_secret'] ?? null) || ($data['clear_turnstile_secret'] ?? false),
        ])->log('Chat security settings updated');

        $this->toast(['type' => 'success', 'message' => __('Chat security settings saved.')]);

        return back();
    }

    public function activity(Request $request): Response
    {
        Gate::authorize('security.manage');

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(ChatSecurityEvent::TYPES))],
            'ip' => ['nullable', 'string', 'max:45'],
        ]);

        $events = ChatSecurityEvent::query()
            ->with(['user:id,name', 'guest:id,name'])
            ->when($filters['type'] ?? null, fn (Builder $q, string $t) => $q->where('type', $t))
            ->when($filters['ip'] ?? null, fn (Builder $q, string $ip) => $q->where('ip_address', $ip))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ChatSecurityEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'label' => $e->typeLabel(),
                'severity' => $e->severity,
                'ip' => $e->ip_address,
                'who' => $e->user->name ?? ($e->guest ? __('Visitor: :name', ['name' => $e->guest->name]) : null),
                'guest_id' => $e->chat_guest_id,
                'conversation_url' => $e->chat_conversation_id ? route('admin.chat.show', $e->chat_conversation_id, absolute: false) : null,
                'details' => $e->details,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/chat/SecurityActivity', [
            'events' => $events,
            'filters' => ['type' => $filters['type'] ?? '', 'ip' => $filters['ip'] ?? ''],
            'types' => collect(ChatSecurityEvent::TYPES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'blocks' => ChatIpBlock::query()->active()->with('creator:id,name')->orderByDesc('id')->limit(200)->get()->map(fn (ChatIpBlock $b) => [
                'id' => $b->id,
                'ip' => $b->ip_address,
                'reason' => $b->reason,
                'automatic' => $b->automatic,
                'by' => $b->creator?->name,
                'expires_at' => $b->expires_at?->toIso8601String(),
                'created_at' => $b->created_at?->toIso8601String(),
            ]),
            'guests' => ChatGuest::query()
                ->where(fn (Builder $q) => $q->where('suspended_until', '>', now())->orWhereIn('id', ChatSecurityEvent::query()->whereNotNull('chat_guest_id')->where('created_at', '>=', now()->subDays(7))->select('chat_guest_id')))
                ->orderByDesc('id')->limit(100)->get()
                ->map(fn (ChatGuest $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'ip' => $g->ip_address,
                    'suspended' => $g->isSuspended(),
                    'suspended_until' => $g->suspended_until?->toIso8601String(),
                    'reason' => $g->suspension_reason,
                    'conversation_url' => $g->chat_conversation_id ? route('admin.chat.show', $g->chat_conversation_id, absolute: false) : null,
                    'last_seen_at' => $g->last_seen_at?->toIso8601String(),
                ]),
        ]);
    }

    public function block(Request $request, ChatGuard $guard): RedirectResponse
    {
        Gate::authorize('security.manage');

        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:87600'],
        ]);

        $guard->blockIp($data['ip'], $data['reason'], isset($data['hours']) ? (int) $data['hours'] : null, $request->user());

        activity('chat-security')->causedBy($request->user())->withProperties(['ip' => $data['ip'], 'hours' => $data['hours'] ?? null])->log('IP address blocked from chat');
        $this->toast(['type' => 'success', 'message' => __('IP address blocked.')]);

        return back();
    }

    public function unblock(Request $request, ChatIpBlock $block, ChatGuard $guard): RedirectResponse
    {
        Gate::authorize('security.manage');

        $ip = $block->ip_address;
        $block->delete();
        $guard->record(new ChatActor($request->user(), null, $ip), 'ip_blocked', 'info', null, null, ['unblocked' => true]);

        activity('chat-security')->causedBy($request->user())->withProperties(['ip' => $ip])->log('IP address unblocked from chat');
        $this->toast(['type' => 'success', 'message' => __('IP address unblocked.')]);

        return back();
    }

    public function suspend(Request $request, ChatGuest $guest, ChatGuard $guard): RedirectResponse
    {
        Gate::authorize('security.manage');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:87600'],
        ]);

        $guard->suspendGuest($guest, $data['reason'], isset($data['hours']) ? (int) $data['hours'] : null, $request->user());

        activity('chat-security')->causedBy($request->user())->withProperties(['guest_id' => $guest->id])->log('Website visitor suspended from chat');
        $this->toast(['type' => 'success', 'message' => __('Visitor suspended.')]);

        return back();
    }

    public function unsuspend(Request $request, ChatGuest $guest): RedirectResponse
    {
        Gate::authorize('security.manage');

        $guest->forceFill(['suspended_until' => null, 'suspension_reason' => null])->save();

        activity('chat-security')->causedBy($request->user())->withProperties(['guest_id' => $guest->id])->log('Website visitor unsuspended');
        $this->toast(['type' => 'success', 'message' => __('Visitor can chat again.')]);

        return back();
    }
}
