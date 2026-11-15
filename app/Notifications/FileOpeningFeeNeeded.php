<?php

namespace App\Notifications;

use App\Models\StudentFile;

/**
 * The file-opening fee could not be raised automatically because no amount
 * is configured for it. The counselor raises it by hand.
 */
class FileOpeningFeeNeeded extends AppNotification
{
    public function __construct(public readonly StudentFile $file) {}

    protected function title(object $notifiable): string
    {
        return __('Raise the file-opening fee');
    }

    protected function body(object $notifiable): string
    {
        return __('File :number was opened. No default amount is set for the file-opening fee, so please raise the payment request manually.', ['number' => $this->file->file_number]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('counselor.payment-requests.create', ['student' => $this->file->student_id, 'file' => $this->file->id], absolute: false);
    }

    protected function icon(): string
    {
        return 'request_quote';
    }

    protected function category(): string
    {
        return 'payment';
    }
}
