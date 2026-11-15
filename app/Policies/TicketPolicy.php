<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.manage');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.assign');
    }
}
