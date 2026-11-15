<?php

namespace App\Support\Presenters;

use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PaymentStatusHistory;
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
            'method' => $p->method->name,
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
        $next = null;

        foreach ($requests as $r) {
            if ($r->status === PaymentRequestStatus::Cancelled) {
                continue;
            }

            $total += $r->amount;
            $paid += $r->amount_paid;

            if ($r->status->isOpen() && ($next === null || ($r->due_date && (! $next->due_date || $r->due_date->isBefore($next->due_date))))) {
                $next = $r;
            }
        }

        return [
            'total' => Money::format($total, $currency),
            'paid' => Money::format($paid, $currency),
            'due' => Money::format(max(0, $total - $paid), $currency),
            'due_minor' => max(0, $total - $paid),
            'next' => $next ? [
                'type' => $next->type->name,
                'invoice_number' => $next->invoice_number,
                'amount' => Money::format($next->outstanding(), $next->currency),
                'due_date' => $next->due_date?->toDateString(),
            ] : null,
        ];
    }
}
