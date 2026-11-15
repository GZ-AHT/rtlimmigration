<?php

namespace App\Notifications;

use App\Models\StudentFile;

class StudentFileOpenedNotification extends AppNotification
{
    public function __construct(public readonly StudentFile $file) {}

    protected function title(object $notifiable): string
    {
        return __('Your file has been opened');
    }

    protected function body(object $notifiable): string
    {
        return __('File :number', ['number' => $this->file->file_number])
            .($this->file->service ? ' · '.$this->file->service->name : '')
            .($this->file->country ? ' · '.$this->file->country->name : '');
    }

    protected function url(object $notifiable): ?string
    {
        return route('portal.file', absolute: false);
    }

    protected function icon(): string
    {
        return 'folder_open';
    }

    protected function category(): string
    {
        return 'application';
    }
}
