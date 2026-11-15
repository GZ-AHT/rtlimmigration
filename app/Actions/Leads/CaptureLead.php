<?php

namespace App\Actions\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Records a new lead from the website form or staff entry (REQ §11 step 1),
 * flagging likely duplicates (REQ §7 "Duplicate Lead Detection"). Duplicates
 * are flagged, not rejected: the same person may genuinely enquire again.
 */
class CaptureLead
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Keys: name (required), email, phone, country_id, service_id,
     * contact_preference, message, consent, ip_address, user_agent.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, LeadSource $source, ?User $creator = null): Lead
    {
        return DB::transaction(function () use ($data, $source, $creator) {
            $email = Lead::normalizeEmail($data['email'] ?? null);
            $phone = Lead::normalizePhone($data['phone'] ?? null);

            return Lead::create([
                'lead_number' => $this->numbers->next('lead'),
                'name' => trim($data['name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email_normalized' => $email,
                'phone_normalized' => $phone,
                'country_id' => $data['country_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'contact_preference' => $data['contact_preference'] ?? null,
                'message' => $data['message'] ?? null,
                'source' => $source,
                'status' => LeadStatus::New,
                'duplicate_of_id' => $this->findDuplicate($email, $phone)?->id,
                'consent_at' => ($data['consent'] ?? false) ? now() : null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => isset($data['user_agent']) ? mb_substr($data['user_agent'], 0, 512) : null,
                'created_by' => $creator?->id,
            ]);
        });
    }

    /** The earliest existing lead with the same email or phone. */
    public function findDuplicate(?string $email, ?string $phone, ?int $ignoreId = null): ?Lead
    {
        if ($email === null && $phone === null) {
            return null;
        }

        return Lead::withTrashed()
            ->where(fn ($q) => $q
                ->when($email, fn ($q) => $q->orWhere('email_normalized', $email))
                ->when($phone, fn ($q) => $q->orWhere('phone_normalized', $phone)))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->oldest('id')
            ->first();
    }
}
