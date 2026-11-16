<?php

namespace App\Support\Gateways;

use App\Enums\GatewayTransactionStatus;

/** What the provider says about one transaction, after a server-side check. */
final readonly class GatewayVerification
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public GatewayTransactionStatus $status,
        public ?string $reason = null,
        public ?string $validationId = null,
        public ?string $bankTransactionId = null,
        public ?string $cardType = null,
        public array $raw = [],
    ) {}

    /**
     * The provider could not be asked (network error). Nothing is known, so
     * nothing changes; the provider's next callback retries.
     */
    public static function unknown(string $reason): self
    {
        return new self(GatewayTransactionStatus::Initiated, $reason);
    }
}
