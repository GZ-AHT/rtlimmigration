<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatWebhookLog;
use App\Support\Chat\ChatSettings;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §18 "Admin Chat Management", §23.3 "Live Chat Integration". */
class ChatSettingsController extends Controller
{
    public function edit(ChatSettings $settings): Response
    {
        Gate::authorize('chat.manage');

        return Inertia::render('admin/chat/Settings', [
            'settings' => $settings->forAdmin(),
            'webhookUrl' => $settings->webhookUrl(),
            'defaultWebhookUrl' => route('chat.webhook'),
            'environment' => ChatSettings::currentEnvironment(),
            'maxTags' => ChatSettings::MAX_TAGS,
            'tawkUrl' => ChatSettings::DASHBOARD_URL,
            'deliveries' => ChatWebhookLog::query()->latest('id')->limit(25)->get()->map(fn (ChatWebhookLog $l) => [
                'id' => $l->id,
                'event' => $l->event,
                'external_id' => $l->external_id,
                'signature_valid' => $l->signature_valid,
                'result' => $l->result,
                'deliveries' => $l->deliveries,
                'processed' => $l->processed_at !== null,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request, ChatSettings $settings): RedirectResponse
    {
        Gate::authorize('chat.manage');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            // These two go into the widget script URL, so they are kept to the characters Tawk.to uses.
            'property_id' => ['nullable', 'required_if_accepted:enabled', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
            'widget_id' => ['nullable', 'required_if_accepted:enabled', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/'],
            'show_on_website' => ['required', 'boolean'],
            'show_in_portal' => ['required', 'boolean'],
            'portal_tags' => ['present', 'array', 'max:'.ChatSettings::MAX_TAGS],
            'portal_tags.*' => ['string', 'max:40', 'distinct:ignore_case'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'clear_api_key' => ['boolean'],
            'clear_webhook_secret' => ['boolean'],
            'webhook_urls' => ['sometimes', 'array:'.implode(',', ChatSettings::WEBHOOK_ENVIRONMENTS)],
            'webhook_urls.*' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail) {
                $problem = ChatSettings::webhookUrlProblem(trim((string) $value), Str::after($attribute, '.'));

                if ($problem !== null) {
                    $fail($problem);
                }
            }],
        ], [
            'property_id.regex' => __('The property ID is the long code of letters and numbers in your Tawk.to widget link.'),
            'widget_id.regex' => __('The widget ID is the short code after the property ID in your Tawk.to widget link.'),
            'property_id.required_if_accepted' => __('Enter the property ID before switching live chat on.'),
            'widget_id.required_if_accepted' => __('Enter the widget ID before switching live chat on.'),
        ]);

        $urlsBefore = $settings->webhookUrls();

        $settings->save($data);

        // Which fields changed, never the secrets themselves.
        activity('chat')->causedBy($request->user())->withProperties([
            'enabled' => (bool) $data['enabled'],
            'api_key_changed' => filled($data['api_key'] ?? null) || ($data['clear_api_key'] ?? false),
            'webhook_secret_changed' => filled($data['webhook_secret'] ?? null) || ($data['clear_webhook_secret'] ?? false),
            'webhook_urls_changed' => array_keys(array_diff_assoc($settings->webhookUrls(), $urlsBefore)),
        ])->log('Live chat settings updated');

        $this->toast(['type' => 'success', 'message' => __('Live chat settings saved.')]);

        return back();
    }
}
