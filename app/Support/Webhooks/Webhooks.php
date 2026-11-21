<?php

namespace App\Support\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\ApplicationStatusHistory;
use App\Models\Lead;
use App\Models\PartnerApplication;
use App\Models\PaymentStatusHistory;
use App\Models\Student;
use App\Models\Webhook;
use Throwable;

/** Tells webhooks when something happens (REQ §23.3). Payloads hold ids and statuses only. */
class Webhooks
{
    /** @param array<string, mixed> $data */
    public static function fire(string $event, array $data): void
    {
        try {
            Webhook::query()->where('is_active', true)->get()
                ->filter(fn (Webhook $w) => in_array($event, $w->events, true))
                ->each(fn (Webhook $w) => dispatch(new DeliverWebhook($w->id, $event, $data)));
        } catch (Throwable $e) {
            // A webhook problem must never break the action that triggered it.
            report($e);
        }
    }

    /** Called once at boot. */
    public static function listen(): void
    {
        Lead::created(fn (Lead $l) => self::fire('lead.created', ['lead_id' => $l->id, 'status' => $l->status->value]));
        Student::created(fn (Student $s) => self::fire('student.created', ['student_id' => $s->id]));
        PartnerApplication::created(fn (PartnerApplication $a) => self::fire('partner.application_received', ['application_id' => $a->id]));
        ApplicationStatusHistory::created(fn (ApplicationStatusHistory $h) => self::fire('application.status_changed', ['application_id' => $h->application_id, 'from' => $h->from_stage_name, 'to' => $h->to_stage_name, 'event' => $h->event]));
        PaymentStatusHistory::created(fn (PaymentStatusHistory $h) => self::fire('payment.status_changed', ['subject' => strtolower(class_basename($h->subject_type)), 'subject_id' => $h->subject_id, 'from' => $h->from_status, 'to' => $h->to_status]));
    }
}
