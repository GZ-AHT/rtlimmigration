<?php

use App\Support\Gateways\SslCommerzGateway;

describe('SslCommerzGateway signature verification', function () {
    $config = [
        'store_id' => 'teststore',
        'store_password' => 'secret123',
        'sandbox' => true,
    ];

    it('verifies valid MD5 signature correctly', function () use ($config) {
        $gateway = new SslCommerzGateway($config);

        $payload = [
            'tran_id' => 'RTL01TEST',
            'val_id' => 'VAL123',
            'amount' => '100.00',
            'currency' => 'BDT',
            'status' => 'VALID',
        ];

        // Generate valid signature using SSLCommerz specification:
        // verify_key is a comma-separated list of keys
        $keys = array_keys($payload);
        $payload['verify_key'] = implode(',', $keys);

        $data = $payload;
        $data['store_passwd'] = md5('secret123');
        ksort($data);

        $params = [];
        foreach ($data as $k => $v) {
            if ($k !== 'verify_key') {
                $params[] = $k.'='.$v;
            }
        }
        // Actually verify_key lists the keys included
        // In our gateway implementation:
        // $keys = array_filter(array_map('trim', explode(',', $verifyKey)));
        // foreach ($keys as $key) { if (array_key_exists($key, $payload)) $data[$key] = (string) $payload[$key]; }
        // $data['store_passwd'] = md5($password);
        // ksort($data);
        $dataToHash = [];
        foreach ($keys as $k) {
            $dataToHash[$k] = (string) $payload[$k];
        }
        $dataToHash['store_passwd'] = md5('secret123');
        ksort($dataToHash);

        $hashParts = [];
        foreach ($dataToHash as $k => $v) {
            $hashParts[] = $k.'='.$v;
        }
        $hashString = implode('&', $hashParts);
        $payload['verify_sign'] = md5($hashString);

        expect($gateway->verifySignature($payload))->toBeTrue();
    });

    it('verifies valid SHA256 signature correctly', function () use ($config) {
        $gateway = new SslCommerzGateway($config);

        $payload = [
            'tran_id' => 'RTL02SHA',
            'val_id' => 'VAL999',
            'amount' => '250.00',
            'currency' => 'BDT',
            'status' => 'VALID',
        ];

        $keys = array_keys($payload);
        $payload['verify_key'] = implode(',', $keys);

        $dataToHash = [];
        foreach ($keys as $k) {
            $dataToHash[$k] = (string) $payload[$k];
        }
        $dataToHash['store_passwd'] = md5('secret123');
        ksort($dataToHash);

        $hashParts = [];
        foreach ($dataToHash as $k => $v) {
            $hashParts[] = $k.'='.$v;
        }
        $hashString = implode('&', $hashParts);
        $payload['verify_sign'] = 'dummy-md5';
        $payload['verify_sign_sha2'] = hash('sha256', $hashString);

        expect($gateway->verifySignature($payload))->toBeTrue();
    });

    it('rejects tampered payload', function () use ($config) {
        $gateway = new SslCommerzGateway($config);

        $payload = [
            'tran_id' => 'RTL01TEST',
            'val_id' => 'VAL123',
            'amount' => '100.00',
            'currency' => 'BDT',
            'status' => 'VALID',
            'verify_key' => 'tran_id,val_id,amount,currency,status',
            'verify_sign' => '0123456789abcdef0123456789abcdef',
        ];

        expect($gateway->verifySignature($payload))->toBeFalse();
    });

    it('returns false when signature fields are missing', function () use ($config) {
        $gateway = new SslCommerzGateway($config);

        expect($gateway->verifySignature([]))->toBeFalse()
            ->and($gateway->verifySignature(['verify_sign' => 'abc']))->toBeFalse()
            ->and($gateway->verifySignature(['verify_key' => 'tran_id']))->toBeFalse();
    });
});
