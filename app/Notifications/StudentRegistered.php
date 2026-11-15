<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Tells administrators that a client signed up on the website (REQ §2
 * "Recent Student Registrations", §20 system notifications).
 */
class StudentRegistered extends AppNotification
{
    public function __construct(public readonly User $student) {}

    protected function title(object $notifiable): string
    {
        return __('New student registration');
    }

    protected function body(object $notifiable): string
    {
        return __(':name (:email) created an account.', ['name' => $this->student->name, 'email' => $this->student->email]);
    }

    protected function url(object $notifiable): ?string
    {
        return $notifiable instanceof User && $notifiable->can('users.view')
            ? route('admin.users.show', $this->student, absolute: false)
            : null;
    }

    protected function icon(): string
    {
        return 'person_add';
    }

    protected function category(): string
    {
        return 'student';
    }
}
