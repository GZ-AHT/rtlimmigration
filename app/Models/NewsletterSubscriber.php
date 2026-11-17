<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * REQ §21.4 / §21.7 Newsletter Subscription. Unsubscribing keeps the row
 * (so the address is not re-added by mistake) and flips the status.
 *
 * @property int $id
 * @property string $email
 * @property string $status
 * @property string|null $source
 * @property string|null $ip_address
 * @property string $unsubscribe_token
 * @property CarbonInterface|null $unsubscribed_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['email', 'status', 'source', 'ip_address', 'unsubscribe_token', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }
}
