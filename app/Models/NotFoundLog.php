<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A public address that answered "not found", with how often and from where.
 *
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property string|null $referrer
 * @property CarbonInterface $first_seen_at
 * @property CarbonInterface $last_seen_at
 */
#[Fillable(['path', 'hits', 'referrer', 'first_seen_at', 'last_seen_at'])]
class NotFoundLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['hits' => 'integer', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
