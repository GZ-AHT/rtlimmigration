<?php

namespace App\Models;

use App\Enums\GatewayTransactionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay a request online (REQ §12.3). Becomes a payment only
 * after the gateway confirms it server-side (REQ §12.5 rules 4–5).
 *
 * @property int $id
 * @property string $gateway
 * @property string $tran_id
 * @property int $payment_request_id
 * @property int $student_id
 * @property int|null $payment_id
 * @property int $amount
 * @property string $currency
 * @property GatewayTransactionStatus $status
 * @property string|null $session_key
 * @property string|null $validation_id
 * @property string|null $bank_tran_id
 * @property string|null $card_type
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property int|null $initiated_by
 * @property CarbonInterface|null $settled_at
 * @property CarbonInterface|null $created_at
 * @property-read PaymentRequest $request
 * @property-read Student $student
 * @property-read Payment|null $payment
 */
#[Fillable([
    'gateway', 'tran_id', 'payment_request_id', 'student_id', 'payment_id', 'amount', 'currency', 'status',
    'session_key', 'validation_id', 'bank_tran_id', 'card_type', 'failure_reason', 'payload', 'initiated_by', 'settled_at',
])]
class GatewayTransaction extends Model
{
    protected $attributes = ['status' => 'initiated', 'currency' => 'BDT'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => GatewayTransactionStatus::class,
            'payload' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PaymentRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
