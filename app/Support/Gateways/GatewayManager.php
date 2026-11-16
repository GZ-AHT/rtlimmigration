<?php

namespace App\Support\Gateways;

/** Resolves gateway drivers from config/payments.php. */
class GatewayManager
{
    /** The gateway students are offered, or null when online payment is off. */
    public function active(): ?PaymentGateway
    {
        $gateway = $this->driver((string) config('payments.gateway'));

        return $gateway?->isConfigured() ? $gateway : null;
    }

    public function driver(string $key): ?PaymentGateway
    {
        return match ($key) {
            'sslcommerz' => new SslCommerzGateway((array) config('payments.gateways.sslcommerz')),
            default => null,
        };
    }

    public function methodSlug(string $key): string
    {
        return (string) config("payments.gateways.{$key}.method_slug");
    }
}
