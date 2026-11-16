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
        // 1. If payload carries a cryptographic signature (IPN/callback), verify it
        if (isset($payload['verify_sign']) && ! $this->verifySignature($payload)) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, 'SSLCommerz callback signature verification failed.');
        }

        $claimed = strtoupper((string) ($payload['status'] ?? ''));
        $validationId = $payload['val_id'] ?? null;

        if (! is_string($validationId) || $validationId === '') {
            // Before accepting any failure or cancellation claim, check if SSLCommerz
            // actually validated this transaction (guards against fake cancel/fail payloads).
            $data = $this->findValidated($transaction);

            if ($data !== null) {
                $validationId = (string) ($data['val_id'] ?? '');
            } elseif (in_array($claimed, ['FAILED', 'CANCELLED', 'UNATTEMPTED', 'EXPIRED'], true)) {
                return new GatewayVerification(
                    $claimed === 'CANCELLED' ? GatewayTransactionStatus::Cancelled : GatewayTransactionStatus::Failed,
                    is_string($payload['error'] ?? null) ? mb_substr($payload['error'], 0, 250) : null,
                );
            } else {
                return GatewayVerification::unknown('SSLCommerz has no validated payment for this transaction yet.');
            }
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

        // 2. Prevent replay attacks: validation_id cannot belong to another already-settled transaction
        if (filled($validationId)) {
            $alreadySettled = GatewayTransaction::query()
                ->where('validation_id', $validationId)
                ->where('id', '!=', $transaction->id)
                ->whereIn('status', [GatewayTransactionStatus::Paid, GatewayTransactionStatus::Review])
                ->exists();

            if ($alreadySettled) {
                return new GatewayVerification(
                    GatewayTransactionStatus::Invalid,
                    'Replay attack detected: validation ID was already settled on another transaction.',
                    ...$details
                );
            }
        }

        // 3. Strict Amount and Currency Verification
        $currency = filled($data['currency_type'] ?? null) ? (string) $data['currency_type'] : (string) ($data['currency'] ?? '');
        $amount = filled($data['currency_amount'] ?? null) ? (string) $data['currency_amount'] : (string) ($data['amount'] ?? '');

        if (strcasecmp($currency, $transaction->currency) !== 0) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, "The validated currency ({$currency}) does not match expected ({$transaction->currency}).", ...$details);
        }

        if (! is_numeric($amount)) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, 'The validated amount is not numeric.', ...$details);
        }

        $paidCents = (int) round(((float) $amount) * 100);

        if ($paidCents < $transaction->amount) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, "Underpayment detected: expected {$transaction->amount} {$transaction->currency}, validated {$paidCents} {$currency}.", ...$details);
        }

        if ($paidCents !== $transaction->amount) {
            return new GatewayVerification(GatewayTransactionStatus::Invalid, "Amount mismatch: expected {$transaction->amount} {$transaction->currency}, validated {$paidCents} {$currency}.", ...$details);
        }

        // risk_level 1: SSLCommerz advises holding the service until checked.
        if ((string) ($data['risk_level'] ?? '0') === '1') {
            return new GatewayVerification(GatewayTransactionStatus::Review, (string) ($data['risk_title'] ?? 'Flagged as risky by SSLCommerz.'), ...$details);
        }

        return new GatewayVerification(GatewayTransactionStatus::Paid, null, ...$details);
    }

    /**
     * Verify the cryptographic signature of an IPN / callback payload (verify_sign / verify_key).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        $verifySign = $payload['verify_sign'] ?? null;
        $verifyKey = $payload['verify_key'] ?? null;

        if (! is_string($verifySign) || $verifySign === '' || ! is_string($verifyKey) || $verifyKey === '') {
            return false;
        }

        $password = (string) ($this->config['store_password'] ?? '');
        if ($password === '') {
            return false;
        }

        $keys = array_filter(array_map('trim', explode(',', $verifyKey)));
        $data = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = (string) $payload[$key];
            }
        }

        $data['store_passwd'] = md5($password);
        ksort($data);

        $params = [];
        foreach ($data as $key => $val) {
            $params[] = $key.'='.$val;
        }
        $hashString = implode('&', $params);

        $verifySignSha2 = $payload['verify_sign_sha2'] ?? null;
        if (is_string($verifySignSha2) && $verifySignSha2 !== '') {
            $computedSha2 = hash('sha256', $hashString);
            if (hash_equals(strtolower($computedSha2), strtolower($verifySignSha2))) {
                return true;
            }
        }

        $computedMd5 = md5($hashString);

        return hash_equals(strtolower($computedMd5), strtolower($verifySign));
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
