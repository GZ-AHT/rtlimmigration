<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** Sends one event to one webhook, signed with its secret, and records what happened (REQ §23.3). */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $data */
    public function __construct(public int $webhookId, public string $event, public array $data, public string $deliveryId = '') {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $webhook = Webhook::query()->find($this->webhookId);

        if ($webhook === null || ! $webhook->is_active) {
            return;
        }

        $deliveryId = $this->deliveryId !== '' ? $this->deliveryId : (string) Str::uuid();
        $body = json_encode(['id' => $deliveryId, 'event' => $this->event, 'created_at' => now()->toIso8601String(), 'data' => $this->data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $started = microtime(true);
        $status = null;
        $error = null;

        // Checked again at delivery: DNS can change after the address was saved.
        if ($problem = WebhookUrl::problem($webhook->url)) {
            $error = $problem;
        } else {
            try {
                $response = Http::withBody($body, 'application/json')->timeout(10)->withoutRedirecting()->withUserAgent('RTL-Webhooks/1.0')->withHeaders([
                    'X-RTL-Event' => $this->event,
                    'X-RTL-Delivery' => $deliveryId,
                    'X-RTL-Timestamp' => $timestamp,
                    'X-RTL-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret),
                ])->post($webhook->url);
                $status = $response->status();
                $error = $response->successful() ? null : 'HTTP '.$status;
            } catch (Throwable $e) {
                $error = Str::limit(Str::before($e->getMessage(), ' for '), 120, '');
            }
        }

        $ok = $error === null;
        WebhookDelivery::create(['webhook_id' => $webhook->id, 'event' => $this->event, 'delivery_id' => $deliveryId, 'status_code' => $status, 'succeeded' => $ok, 'error' => $error,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'attempt' => $this->attempts()]);

        $failures = $ok ? 0 : $webhook->failure_count + 1;
        $webhook->update(['failure_count' => $failures, 'last_status' => $status, 'last_delivered_at' => $ok ? now() : $webhook->last_delivered_at,
            'is_active' => $failures < Webhook::MAX_FAILURES]);

        if (! $ok) {
            $this->fail(new \RuntimeException($error));
        }
    }
}
