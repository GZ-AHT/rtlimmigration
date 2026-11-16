<?php

namespace App\Support\Presenters;

use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PaymentStatusHistory;
use App\Models\Refund;
use App\Support\Money;

class PaymentPresenter
{
    /** @return array<string, mixed> */
    public static function request(PaymentRequest $r): array
    {
        return [
            'id' => $r->id,
            'invoice_number' => $r->invoice_number,
            'type' => $r->type->name,
            'description' => $r->description,
            'student' => $r->relationLoaded('student') ? $r->student->user->name : null,
            'student_number' => $r->relationLoaded('student') ? $r->student->student_number : null,
            'file_number' => $r->file?->file_number,
            'amount' => $r->amount,
            'amount_label' => Money::format($r->amount, $r->currency),
            'paid_label' => Money::format($r->amount_paid, $r->currency),
            'refunded' => $r->amount_refunded,
            'refunded_label' => Money::format($r->amount_refunded, $r->currency),
            'outstanding' => $r->outstanding(),
            'outstanding_label' => Money::format($r->outstanding(), $r->currency),
            'currency' => $r->currency,
            'due_date' => $r->due_date?->toDateString(),
            'overdue' => $r->due_date !== null && $r->due_date->isBefore(today()) && $r->status->isOpen(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_tone' => $r->status->tone(),
            'requested_by' => $r->requester?->name,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function payment(Payment $p): array
    {
        return [
            'id' => $p->id,
            'invoice_number' => $p->relationLoaded('request') ? $p->request->invoice_number : null,
            'type' => $p->relationLoaded('request') && $p->request->relationLoaded('type') ? $p->request->type->name : null,
            'student' => $p->relationLoaded('student') ? $p->student->user->name : null,
            'student_number' => $p->relationLoaded('student') ? $p->student->student_number : null,
            'amount' => $p->amount,
            'amount_label' => Money::format($p->amount, $p->currency),
            'refunded' => $p->amount_refunded,
            'refunded_label' => Money::format($p->amount_refunded, $p->currency),
            'method' => $p->method->name,
            'method_kind' => $p->method->kind->value,
            'transaction_reference' => $p->transaction_reference,
            'paid_on' => $p->paid_on->toDateString(),
            'has_evidence' => $p->evidence_path !== null,
            'evidence_name' => $p->evidence_original_name,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'status_tone' => $p->status->tone(),
            'rejection_reason' => $p->rejection_reason,
            'receipt_number' => $p->receipt_number,
            'submitted_by' => $p->submitter?->name,
            'verified_by' => $p->verifier?->name,
            'confirmed_by' => $p->confirmer?->name,
            'confirmed_at' => $p->confirmed_at?->toIso8601String(),
            'notes' => $p->notes,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function history(PaymentStatusHistory $h, string $labelFor): array
    {
        $label = fn (?string $status) => $status === null ? null : match ($labelFor) {
            'payment' => PaymentStatus::tryFrom($status)?->label() ?? $status,
            'refund' => RefundStatus::tryFrom($status)?->label() ?? $status,
            default => PaymentRequestStatus::tryFrom($status)?->label() ?? $status,
        };

        return [
            'id' => $h->id,
            'from' => $label($h->from_status),
            'to' => $label($h->to_status),
            'by' => $h->changer?->name,
            'note' => $h->note,
            'at' => $h->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function refund(Refund $r): array
    {
        return [
            'id' => $r->id,
            'refund_number' => $r->refund_number,
            'invoice_number' => $r->relationLoaded('request') ? $r->request->invoice_number : null,
            'type' => $r->relationLoaded('request') && $r->request->relationLoaded('type') ? $r->request->type->name : null,
            'receipt_number' => $r->relationLoaded('payment') ? $r->payment->receipt_number : null,
            'student' => $r->relationLoaded('student') ? $r->student->user->name : null,
            'student_number' => $r->relationLoaded('student') ? $r->student->student_number : null,
            'amount' => $r->amount,
            'amount_label' => Money::format($r->amount, $r->currency),
            'reason' => $r->reason,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_tone' => $r->status->tone(),
            'requested_by' => $r->requester?->name,
            'approved_by' => $r->approver?->name,
            'approved_at' => $r->approved_at?->toIso8601String(),
            'decision_note' => $r->decision_note,
            'processed_by' => $r->processor?->name,
            'processed_at' => $r->processed_at?->toIso8601String(),
            'method' => $r->method?->name,
            'transaction_reference' => $r->transaction_reference,
            'notes' => $r->notes,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function gatewayTransaction(GatewayTransaction $t): array
    {
        return [
            'id' => $t->id,
            'gateway' => $t->gateway,
            'tran_id' => $t->tran_id,
            'invoice_number' => $t->request->invoice_number,
            'student' => $t->student->user->name,
            'student_number' => $t->student->student_number,
            'amount_label' => Money::format($t->amount, $t->currency),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'status_tone' => $t->status->tone(),
            'bank_tran_id' => $t->bank_tran_id,
            'card_type' => $t->card_type,
            'failure_reason' => $t->failure_reason,
            'receipt_number' => $t->payment?->receipt_number,
            'created_at' => $t->created_at?->toIso8601String(),
            'settled_at' => $t->settled_at?->toIso8601String(),
        ];
    }

    /**
     * A student's money at a glance (REQ §10.1 "Total Fees", "Paid Amount",
     * "Due Amount", "Next Payment").
     *
     * @param  iterable<PaymentRequest>  $requests
     * @return array<string, mixed>
     */
    public static function totals(iterable $requests, string $currency = 'BDT'): array
    {
        $total = 0;
        $paid = 0;
        $refunded = 0;
        $next = null;

        foreach ($requests as $r) {
            if ($r->status === PaymentRequestStatus::Cancelled) {
                continue;
            }

            $total += $r->amount;
            $paid += $r->amount_paid;
            $refunded += $r->amount_refunded;

            if ($r->status->isOpen() && ($next === null || ($r->due_date && (! $next->due_date || $r->due_date->isBefore($next->due_date))))) {
                $next = $r;
            }
        }

        return [
            'total' => Money::format($total, $currency),
            'paid' => Money::format($paid, $currency),
            'due' => Money::format(max(0, $total - $paid), $currency),
            'due_minor' => max(0, $total - $paid),
            'refunded' => Money::format($refunded, $currency),
            'refunded_minor' => $refunded,
            'next' => $next ? [
                'type' => $next->type->name,
                'invoice_number' => $next->invoice_number,
                'amount' => Money::format($next->outstanding(), $next->currency),
                'due_date' => $next->due_date?->toDateString(),
            ] : null,
        ];
    }
}
