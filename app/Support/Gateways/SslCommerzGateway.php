<?php

namespace App\Support\Gateways;

use App\Enums\GatewayTransactionStatus;
use App\Models\GatewayTransaction;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * SSLCommerz hosted checkout (v4 API). One merchant account covers cards,
 * bKash, Nagad, Rocket and internet banking.
 *
 *  - initiate():  POST gwprocess/v4/api.php. The payer reaches GatewayPageURL
 *                 by redirect ("hosted") or in SSLCommerz's Easy Checkout
 *                 popup ("easy", their embed.min.js). Same session either way.
 *  - verify():    GET validator/api/validationserverAPI.php with the val_id,
 *                 or merchantTransIDvalidationAPI.php when a return carries
 *                 only our transaction ID.
 *
 * The store password is only ever sent server-to-server.
 */
class SslCommerzGateway implements PaymentGateway
{
    /** @param  array{store_id?: string|null, store_password?: string|null, sandbox?: bool, checkout?: string|null}  $config */
    public function __construct(private array $config) {}

    public function key(): string
    {
        return 'sslcommerz';
    }

    public function label(): string
    {
        return 'SSLCommerz';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['store_id'] ?? null) && filled($this->config['store_password'] ?? null);
    }

    public function checkout(): array
    {
        if (($this->config['checkout'] ?? 'easy') !== 'easy') {
            return ['mode' => 'hosted', 'script' => null];
        }

        return [
            'mode' => 'easy',
            'script' => ($this->config['sandbox'] ?? true)
                ? 'https://sandbox.sslcommerz.com/embed.min.js'
                : 'https://seamless-epay.sslcommerz.com/embed.min.js',
        ];
    }

    public function initiate(GatewayTransaction $transaction, array $urls): GatewayCheckout
    {
        $transaction->loadMissing(['student.user', 'student.profile', 'request.type']);
        $user = $transaction->student->user;
        $profile = $transaction->student->profile;

        try {
            $response = Http::asForm()->timeout(20)->post($this->baseUrl().'/gwprocess/v4/api.php', [
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'total_amount' => Money::toDecimal($transaction->amount),
                'currency' => $transaction->currency,
                'tran_id' => $transaction->tran_id,
                'success_url' => $urls['success'],
                'fail_url' => $urls['fail'],
                'cancel_url' => $urls['cancel'],
                'ipn_url' => $urls['ipn'],
                'cus_name' => $user->name,
                'cus_email' => $user->email,
                'cus_phone' => $transaction->student->phone ?: 'N/A',
                'cus_add1' => $profile?->address_line ?: 'N/A',
                'cus_city' => $profile?->city ?: 'Dhaka',
                'cus_country' => $profile?->country ?: 'Bangladesh',
                'shipping_method' => 'NO',
                'product_name' => $transaction->request->type->name,
                'product_category' => 'Service',
                'product_profile' => 'non-physical-goods',
                // Echoed back in callbacks; handy when reading provider reports.
                'value_a' => $transaction->request->invoice_number,
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('SSLCommerz could not be reached.', previous: $e);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? null) !== 'SUCCESS' || blank($body['GatewayPageURL'] ?? null)) {
            throw new GatewayException((string) (is_array($body) ? ($body['failedreason'] ?? 'SSLCommerz refused the payment session.') : 'SSLCommerz returned an unreadable response.'));
        }

        return new GatewayCheckout(
            (string) $body['GatewayPageURL'],
            isset($body['sessionkey']) ? (string) $body['sessionkey'] : null,
            filled($body['storeLogo'] ?? null) ? (string) $body['storeLogo'] : null,
        );
    }

    public function transactionId(array $payload): ?string
    {
        $id = $payload['tran_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function verify(GatewayTransaction $transaction, array $payload): GatewayVerification
    {
        $claimed = strtoupper((string) ($payload['status'] ?? ''));
        $validationId = $payload['val_id'] ?? null;

        if (! is_string($validationId) || $validationId === '') {
            // The gateway says the payer failed or backed out: no money moved.
            if (in_array($claimed, ['FAILED', 'CANCELLED', 'UNATTEMPTED', 'EXPIRED'], true)) {
                return new GatewayVerification(
                    $claimed === 'CANCELLED' ? GatewayTransactionStatus::Cancelled : GatewayTransactionStatus::Failed,
                    is_string($payload['error'] ?? null) ? mb_substr($payload['error'], 0, 250) : null,
                );
            }

            // A return that names only our transaction (Easy Checkout sends
            // the page back this way): ask the gateway what became of it.
            $data = $this->findValidated($transaction);

            if ($data === null) {
                return GatewayVerification::unknown('SSLCommerz has no validated payment for this transaction yet.');
            }

            $validationId = (string) ($data['val_id'] ?? '');
        } else {
            try {
                $response = Http::timeout(20)->get($this->baseUrl().'/validator/api/validationserverAPI.php', [
                    'val_id' => $validationId,
                    'store_id' => $this->config['store_id'] ?? '',
                    'store_passwd' => $this->config['store_password'] ?? '',
                    'format' => 'json',
                ]);
            } catch (ConnectionException) {
                return GatewayVerification::unknown('SSLCommerz could not be reached to validate the payment.');
            }

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return GatewayVerification::unknown('SSLCommerz returned an unreadable validation response.');
            }
        }

        $status = strtoupper((string) ($data['status'] ?? ''));
        $details = [
            'validationId' => $validationId,
            'bankTransactionId' => isset($data['bank_tran_id']) ? (string) $data['bank_tran_id'] : null,
            'cardType' => isset($data['card_type']) ? (string) $data['card_type'] : null,
            'raw' => array_intersect_key($data, array_flip(['status', 'tran_id', 'val_id', 'amount', 'currency', 'currency_type', 'currency_amount', 'store_amount', 'bank_tran_id', 'card_type', 'card_issuer', 'card_brand', 'tran_date', 'risk_level', 'risk_title'])),
        ];

        if (! in_array($status, ['VALID', 'VALIDATED'], true)) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, 'SSLCommerz did not validate the transaction ('.($status ?: 'no status').').', ...$details);
        }

        if (($data['tran_id'] ?? null) !== $transaction->tran_id) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, 'The validated transaction ID does not match.', ...$details);
        }

        // "currency_type" and "currency_amount" are what we asked for;
        // "amount" is what was settled in BDT.
        $currency = (string) ($data['currency_type'] ?? $data['currency'] ?? '');
        $amount = (string) ($data['currency_amount'] ?? $data['amount'] ?? '');

        if ($currency !== $transaction->currency || ! is_numeric($amount) || (int) round(((float) $amount) * 100) !== $transaction->amount) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, 'The validated amount or currency does not match the invoice.', ...$details);
        }

        // risk_level 1: SSLCommerz advises holding the service until checked.
        if ((string) ($data['risk_level'] ?? '0') === '1') {
            return new GatewayVerification(GatewayTransactionStatus::Review, (string) ($data['risk_title'] ?? 'Flagged as risky by SSLCommerz.'), ...$details);
        }

        return new GatewayVerification(GatewayTransactionStatus::Paid, null, ...$details);
    }

    /**
     * Look the transaction up by our own ID and return its validated
     * record, if the gateway has one.
     *
     * @return array<string, mixed>|null
     */
    private function findValidated(GatewayTransaction $transaction): ?array
    {
        try {
            $response = Http::timeout(20)->get($this->baseUrl().'/validator/api/merchantTransIDvalidationAPI.php', [
                'tran_id' => $transaction->tran_id,
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'format' => 'json',
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $elements = $response->successful() ? $response->json('element') : null;

        if (! is_array($elements)) {
            return null;
        }

        foreach ($elements as $element) {
            if (is_array($element) && in_array(strtoupper((string) ($element['status'] ?? '')), ['VALID', 'VALIDATED'], true)) {
                return $element;
            }
        }

        return null;
    }

    private function baseUrl(): string
    {
        return ($this->config['sandbox'] ?? true) ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
    }
}
