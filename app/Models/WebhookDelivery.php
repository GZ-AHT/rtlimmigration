<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $webhook_id
 * @property string $event
 * @property string $delivery_id
 * @property int|null $status_code
 * @property bool $succeeded
 * @property string|null $error
 * @property int|null $duration_ms
 * @property int $attempt
 * @property CarbonInterface $created_at
 */
#[Fillable(['webhook_id', 'event', 'delivery_id', 'status_code', 'succeeded', 'error', 'duration_ms', 'attempt'])]
class WebhookDelivery extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['succeeded' => 'boolean', 'created_at' => 'datetime'];
    }
}
