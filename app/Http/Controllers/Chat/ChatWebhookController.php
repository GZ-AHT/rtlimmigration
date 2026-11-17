<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\RecordChatEvent;
use App\Http\Controllers\Controller;
use App\Models\ChatWebhookLog;
use App\Support\Chat\ChatSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Tawk.to webhooks (REQ §18 "Use webhooks", "Verify webhook
 * authenticity"). Public and CSRF-exempt, so nothing is acted on until the
 * X-Tawk-Signature header matches the body. Tawk.to retries anything that
 * is not a 2xx for up to 12 hours; a retry carries the same
 * X-Hook-Event-Id, which is how repeats are recognised.
 */
class ChatWebhookController extends Controller
{
    private const MAX_BYTES = 1_048_576;

    public function __invoke(Request $request, ChatSettings $settings, RecordChatEvent $record): JsonResponse
    {
        abort_unless(ChatSettings::tawkEnabled(), 404);

        $raw = (string) $request->getContent();

        if (strlen($raw) > self::MAX_BYTES) {
            Log::warning('Tawk.to webhook rejected: payload too large', ['bytes' => strlen($raw), 'ip' => $request->ip()]);

            return response()->json(['message' => 'Payload too large.'], 413);
        }

        if (! $settings->signatureIsValid($raw, $request->header('X-Tawk-Signature'))) {
            // Kept without the body: an unsigned request is not evidence of anything.
            ChatWebhookLog::create([
                'signature_valid' => false,
                'result' => $settings->hasWebhookSecret() ? 'rejected: bad signature' : 'rejected: no webhook secret set',
                'ip_address' => $request->ip(),
            ]);

            Log::warning('Tawk.to webhook rejected: '.($settings->hasWebhookSecret() ? 'signature does not match the saved webhook secret' : 'no webhook secret saved'), [
                'ip' => $request->ip(),
                'has_signature' => $request->hasHeader('X-Tawk-Signature'),
                'event_id' => $request->header('X-Hook-Event-Id'),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            Log::warning('Tawk.to webhook rejected: body is not JSON', ['ip' => $request->ip()]);
            ChatWebhookLog::create(['signature_valid' => true, 'result' => 'rejected: body is not JSON', 'ip_address' => $request->ip()]);

            return response()->json(['message' => 'Invalid payload.'], 422);
        }

        $eventId = $request->header('X-Hook-Event-Id');
        $eventId = is_string($eventId) && $eventId !== '' ? mb_substr($eventId, 0, 128) : null;

        $log = $eventId ? ChatWebhookLog::where('event_id', $eventId)->first() : null;

        if ($log?->processed_at) {
            $log->increment('deliveries');

            return response()->json(['status' => 'duplicate']);
        }

        try {
            $log ??= ChatWebhookLog::create([
                'event_id' => $eventId,
                'event' => is_string($payload['event'] ?? null) ? mb_substr($payload['event'], 0, 64) : null,
                'external_id' => RecordChatEvent::externalId($payload),
                'signature_valid' => true,
                'payload' => $payload,
                'ip_address' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same event is being handled by another request right now.
            return response()->json(['status' => 'duplicate']);
        }

        try {
            $log->update(['result' => $record($payload), 'processed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Tawk.to webhook failed', ['event_id' => $eventId, 'event' => $log->event, 'chat' => $log->external_id, 'exception' => $e]);
            $log->update(['result' => 'failed: '.mb_substr($e->getMessage(), 0, 200)]);

            // Not 2xx, so Tawk.to sends it again.
            return response()->json(['message' => 'Could not process the event.'], 500);
        }

        Log::info('Tawk.to webhook processed', ['event_id' => $eventId, 'event' => $log->event, 'chat' => $log->external_id, 'result' => $log->result]);

        return response()->json(['status' => 'ok']);
    }
}
