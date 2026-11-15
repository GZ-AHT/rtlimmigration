<?php

namespace App\Notifications;

use App\Enums\DocumentStatus;
use App\Models\Document;

/** Tells a student their document was verified or rejected (REQ §27 rule 12). */
class DocumentReviewed extends AppNotification
{
    public function __construct(public readonly Document $document) {}

    protected function title(object $notifiable): string
    {
        return $this->document->status === DocumentStatus::Verified
            ? __('Document verified')
            : __('Document needs attention');
    }

    protected function body(object $notifiable): string
    {
        $name = $this->document->displayName();

        return $this->document->status === DocumentStatus::Verified
            ? $name
            : __(':name was not accepted: :reason', ['name' => $name, 'reason' => $this->document->rejection_reason]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('portal.documents.index', absolute: false);
    }

    protected function icon(): string
    {
        return $this->document->status === DocumentStatus::Verified ? 'verified' : 'report';
    }

    protected function category(): string
    {
        return 'student';
    }
}
