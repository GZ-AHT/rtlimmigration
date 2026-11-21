<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somewhere to send events (REQ §23.3 webhook management).
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $failure_count
 * @property CarbonInterface|null $last_delivered_at
 * @property int|null $last_status
 */
#[Fillable(['name', 'url', 'secret', 'events', 'is_active', 'failure_count', 'last_delivered_at', 'last_status', 'created_by'])]
class Webhook extends Model
{
    /** Failed deliveries in a row before a webhook switches itself off. */
    public const MAX_FAILURES = 25;

    /** event => label. Payloads carry ids and statuses only, never personal details. */
    public const EVENTS = [
        'lead.created' => 'A new lead arrives',
        'student.created' => 'A student record is created',
        'application.status_changed' => 'An application changes stage',
        'payment.status_changed' => 'A payment or invoice changes status',
        'partner.application_received' => 'A partner application arrives',
        'ping' => 'Test event',
    ];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean', 'last_delivered_at' => 'datetime'];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
