<?php

namespace App\Notifications;

use App\Models\Student;

/** Tells a counselor a student is now theirs (REQ §8 "Assign Students"). */
class StudentAssigned extends AppNotification
{
    public function __construct(public readonly Student $student) {}

    protected function title(object $notifiable): string
    {
        return __('Student assigned to you');
    }

    protected function body(object $notifiable): string
    {
        return __(':name (:number)', ['name' => $this->student->user->name, 'number' => $this->student->student_number]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('counselor.students.show', $this->student, absolute: false);
    }

    protected function icon(): string
    {
        return 'school';
    }

    protected function category(): string
    {
        return 'student';
    }
}
