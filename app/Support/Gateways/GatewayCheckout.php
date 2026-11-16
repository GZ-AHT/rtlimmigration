<?php

namespace App\Support\Gateways;

final readonly class GatewayCheckout
{
    public function __construct(public string $redirectUrl, public ?string $sessionKey = null, public ?string $logo = null) {}
}
