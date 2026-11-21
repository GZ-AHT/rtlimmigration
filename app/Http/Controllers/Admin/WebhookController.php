<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §23.3 webhook management. Signing secrets are shown once, when created or renewed. */
class WebhookController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('integrations.manage');

        return Inertia::render('admin/system/Webhooks', [
            'webhooks' => Webhook::query()->orderBy('name')->get()->map(fn (Webhook $w) => [
                'id' => $w->id, 'name' => $w->name, 'url' => $w->url, 'events' => $w->events, 'is_active' => $w->is_active,
                'failure_count' => $w->failure_count, 'last_status' => $w->last_status, 'last_delivered_at' => $w->last_delivered_at?->diffForHumans(),
                'deliveries' => $w->deliveries()->latest('id')->limit(8)->get()->map(fn (WebhookDelivery $d) => [
                    'id' => $d->id, 'event' => $d->event, 'ok' => $d->succeeded, 'status' => $d->status_code, 'error' => $d->error, 'ms' => $d->duration_ms, 'attempt' => $d->attempt, 'when' => $d->created_at->diffForHumans(),
                ]),
            ]),
            'events' => collect(Webhook::EVENTS)->except('ping')->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('integrations.manage');

        $secret = 'whsec_'.Str::random(40);
        $webhook = Webhook::create([...$this->validated($request), 'secret' => $secret, 'created_by' => $request->user()->id]);
        activity('security')->causedBy($request->user())->performedOn($webhook)->log('Webhook added');
        Inertia::flash('webhook_secret', ['name' => $webhook->name, 'secret' => $secret]);
        $this->toast(['type' => 'success', 'message' => __('Webhook added. Copy its signing secret now: it is only shown once.')]);

        return back();
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        Gate::authorize('integrations.manage');

        $data = $this->validated($request);
        // Switching it back on clears the failure count, so it gets a fresh start.
        $webhook->update([...$data, ...($data['is_active'] && ! $webhook->is_active ? ['failure_count' => 0] : [])]);
        activity('security')->causedBy($request->user())->performedOn($webhook)->log('Webhook updated');
        $this->toast(['type' => 'success', 'message' => __('Webhook saved.')]);

        return back();
    }

    public function secret(Request $request, Webhook $webhook): RedirectResponse
    {
        Gate::authorize('integrations.manage');

        $secret = 'whsec_'.Str::random(40);
        $webhook->update(['secret' => $secret]);
        activity('security')->causedBy($request->user())->performedOn($webhook)->log('Webhook secret renewed');
        Inertia::flash('webhook_secret', ['name' => $webhook->name, 'secret' => $secret]);
        $this->toast(['type' => 'success', 'message' => __('New signing secret created. Copy it now: it is only shown once.')]);

        return back();
    }

    public function test(Webhook $webhook): RedirectResponse
    {
        Gate::authorize('integrations.manage');

        DeliverWebhook::dispatchSync($webhook->id, 'ping', ['message' => 'This is a test from RTL Immigration.']);
        $ok = $webhook->deliveries()->latest('id')->value('succeeded');
        $this->toast(['type' => $ok ? 'success' : 'error', 'message' => $ok ? __('Test delivered.') : __('The test failed. See the delivery list for why.')]);

        return back();
    }

    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        Gate::authorize('integrations.manage');

        $webhook->delete();
        activity('security')->causedBy($request->user())->log('Webhook deleted');
        $this->toast(['type' => 'success', 'message' => __('Webhook deleted.')]);

        return back();
    }

    /** @return array{name: string, url: string, events: list<string>, is_active: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['in:'.implode(',', array_keys(array_diff_key(Webhook::EVENTS, ['ping' => 1])))],
            'is_active' => ['boolean'],
        ]);

        if ($problem = WebhookUrl::problem($data['url'])) {
            throw ValidationException::withMessages(['url' => $problem]);
        }

        return ['name' => $data['name'], 'url' => $data['url'], 'events' => array_values($data['events']), 'is_active' => (bool) ($data['is_active'] ?? true)];
    }
}
