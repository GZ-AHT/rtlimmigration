<?php

namespace App\Support\Chat\Security;

use App\Enums\ModerationStatus;

/** What the content checks decided about a message, and why. */
final readonly class Verdict
{
    /**
     * @param  list<string>  $reasons  codes from ContentInspector::REASONS
     * @param  list<string>  $suspiciousUrls
     */
    public function __construct(
        public ModerationStatus $status,
        public array $reasons = [],
        public array $suspiciousUrls = [],
    ) {}

    public static function approved(): self
    {
        return new self(ModerationStatus::Approved);
    }

    public function with(ModerationStatus $status, string $reason): self
    {
        $order = [ModerationStatus::Approved, ModerationStatus::Flagged, ModerationStatus::Pending, ModerationStatus::Blocked];
        $stronger = array_search($status, $order, true) > array_search($this->status, $order, true) ? $status : $this->status;

        return new self($stronger, array_values(array_unique([...$this->reasons, $reason])), $this->suspiciousUrls);
    }
}
