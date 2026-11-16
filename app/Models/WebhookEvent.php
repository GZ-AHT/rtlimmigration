<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A gateway callback as received (REQ §12.5 rule 9). The same event
 * delivered again only bumps the delivery count.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_key
 * @property string|null $tran_id
 * @property string $source
 * @property array<string, mixed>|null $payload
 * @property string|null $result
 * @property int $deliveries
 * @property CarbonInterface|null $processed_at
 */
#[Fillable(['gateway', 'event_key', 'tran_id', 'source', 'payload', 'result', 'deliveries', 'processed_at'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'deliveries' => 'integer', 'processed_at' => 'datetime'];
    }
}
