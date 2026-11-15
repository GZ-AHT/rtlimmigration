<?php

namespace App\Support;

use App\Enums\CounselorAvailability;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Counselors that work can be assigned to, with their current open lead
 * load so the person assigning can balance work (REQ §7 lead assignment).
 */
class CounselorDirectory
{
    /**
     * @return list<array{value: string, label: string, availability: string, availability_label: string, open_leads: int, capacity: int|null, at_capacity: bool}>
     */
    public function options(): array
    {
        $closed = array_map(fn (LeadStatus $s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => $s->isClosed()));

        return array_values(User::role(Role::Counselor->value)
            ->where('status', UserStatus::Active->value)
            ->with('counselorProfile')
            ->withCount(['assignedLeads as open_leads' => fn ($q) => $q->whereNotIn('status', $closed)])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                $availability = $u->counselorProfile->availability ?? CounselorAvailability::Available;
                $capacity = $u->counselorProfile?->max_active_leads;
                $open = (int) $u->getAttribute('open_leads');

                return [
                    'value' => (string) $u->id,
                    'label' => $u->name,
                    'availability' => $availability->value,
                    'availability_label' => $availability->label(),
                    'open_leads' => $open,
                    'capacity' => $capacity,
                    'at_capacity' => $capacity !== null && $open >= $capacity,
                ];
            })
            ->all());
    }
}
