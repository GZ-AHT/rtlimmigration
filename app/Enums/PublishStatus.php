<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Carbon\CarbonInterface;

/**
 * Draft or published, for blog posts and pages (REQ §4 / §5). A published
 * record whose date is still in the future is shown as "Scheduled" and
 * goes live on its own when the date passes; no job is needed.
 */
enum PublishStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array{key: string, label: string, tone: string} */
    public function state(?CarbonInterface $publishedAt): array
    {
        return match (true) {
            $this === self::Draft => ['key' => 'draft', 'label' => 'Draft', 'tone' => 'neutral'],
            $publishedAt !== null && $publishedAt->isFuture() => ['key' => 'scheduled', 'label' => 'Scheduled', 'tone' => 'info'],
            default => ['key' => 'published', 'label' => 'Published', 'tone' => 'success'],
        };
    }
}
