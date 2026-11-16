<?php

namespace App\Support\Gateways;

use App\Models\GatewayTransaction;

/**
 * An online payment provider (REQ §12.3). SSLCommerz is the first driver;
 * aamarPay, bKash and Nagad can be added by implementing this.
 */
interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /** Credentials are present, so students can be offered online payment. */
    public function isConfigured(): bool;

    /**
     * Open a payment session and return where to send the payer.
     *
     * @param  array{success: string, fail: string, cancel: string, ipn: string}  $urls
     *
     * @throws GatewayException
     */
    public function initiate(GatewayTransaction $transaction, array $urls): GatewayCheckout;

    /**
     * How the payer reaches the gateway: "hosted" sends the browser to the
     * gateway's page; "easy" opens the gateway in a popup over our page
     * using the provider's script, whose URL is returned here.
     *
     * @return array{mode: 'hosted'|'easy', script: string|null}
     */
    public function checkout(): array;

    /**
     * The provider's transaction ID in a callback, if it carries one.
     *
     * @param  array<string, mixed>  $payload
     */
    public function transactionId(array $payload): ?string;

    /**
     * Decide what a callback means by asking the provider directly. Nothing
     * in $payload is trusted beyond identifying which transaction to check
     * (REQ §12.5 rules 4 and 5).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(GatewayTransaction $transaction, array $payload): GatewayVerification;
}
