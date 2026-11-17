<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One Tawk.to webhook event as received (REQ §18, §26 chat_webhook_logs).
 * A retry of the same event only bumps the delivery count.
 *
 * @property int $id
 * @property string|null $event_id
 * @property string|null $event
 * @property string|null $external_id
 * @property bool $signature_valid
 * @property array<string, mixed>|null $payload
 * @property string|null $result
 * @property int $deliveries
 * @property string|null $ip_address
 * @property CarbonInterface|null $processed_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['event_id', 'event', 'external_id', 'signature_valid', 'payload', 'result', 'deliveries', 'ip_address', 'processed_at'])]
class ChatWebhookLog extends Model
{
    protected function casts(): array
    {
        return ['signature_valid' => 'boolean', 'payload' => 'array', 'deliveries' => 'integer', 'processed_at' => 'datetime'];
    }
}
