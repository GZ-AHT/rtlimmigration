<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

/**
 * REQ §18 "Counselor Chat Access", "Admin Chat Management".
 *
 * Staff: chat managers (chat.view-all, e.g. Lead Manager, Super Admin) see
 * everything; anyone else with chat.access sees conversations assigned to
 * them or belonging to their students. A student sees only their own
 * portal thread. Website visitors are not users; their access is checked
 * by token in GuestChatController.
 */
class ChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chat.access');
    }

    public function view(User $user, ChatConversation $conversation): bool
    {
        if ($this->ownsThread($user, $conversation)) {
            return true;
        }

        return $this->staffCanSee($user, $conversation);
    }

    /** Tags, status, and turning the visitor into a lead. Staff only. */
    public function update(User $user, ChatConversation $conversation): bool
    {
        return $this->staffCanSee($user, $conversation);
    }

    public function assign(User $user, ChatConversation $conversation): bool
    {
        return $user->can('chat.view-all');
    }

    /**
     * Writing in a portal thread: the student it belongs to, or staff who
     * can see it. Website chats: staff who can see them (the visitor posts
     * through GuestChatController with their session token). Tawk.to chats
     * are answered in Tawk.to, never here.
     */
    public function reply(User $user, ChatConversation $conversation): bool
    {
        if ($conversation->isGuest()) {
            return $this->staffCanSee($user, $conversation);
        }

        return $conversation->isPortal()
            && ($this->ownsThread($user, $conversation) || $this->staffCanSee($user, $conversation));
    }

    private function ownsThread(User $user, ChatConversation $conversation): bool
    {
        return $conversation->isPortal()
            && $conversation->student !== null
            && $conversation->student->user_id === $user->id;
    }

    private function staffCanSee(User $user, ChatConversation $conversation): bool
    {
        if (! $user->can('chat.access')) {
            return false;
        }

        return $user->can('chat.view-all')
            || $conversation->assigned_to === $user->id
            || ($conversation->student !== null && $conversation->student->counselor_id === $user->id);
    }
}
