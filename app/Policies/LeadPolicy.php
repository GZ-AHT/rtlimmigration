<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * REQ §8: counselors only access their assigned leads unless granted more.
 * "Granted more" = leads.assign (Lead Manager, Super Admin).
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view') && $this->inScope($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.update') && $this->inScope($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('leads.assign');
    }

    public function transfer(User $user, Lead $lead): bool
    {
        return $user->can('leads.transfer');
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->can('leads.convert') && $this->inScope($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('leads.delete');
    }

    private function inScope(User $user, Lead $lead): bool
    {
        return $user->can('leads.assign') || $lead->counselor_id === $user->id;
    }
}
