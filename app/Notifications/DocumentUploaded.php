<?php

namespace App\Notifications;

use App\Models\Document;

/** Tells the counselor a student uploaded a document to review. */
class DocumentUploaded extends AppNotification
{
    public function __construct(public readonly Document $document) {}

    protected function title(object $notifiable): string
    {
        return __('New document to review');
    }

    protected function body(object $notifiable): string
    {
        return __(':student uploaded :document', ['student' => $this->document->student->user->name, 'document' => $this->document->displayName()]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('counselor.students.show', ['student' => $this->document->student_id, 'tab' => 'documents'], absolute: false);
    }

    protected function icon(): string
    {
        return 'upload_file';
    }

    protected function category(): string
    {
        return 'student';
    }
}
