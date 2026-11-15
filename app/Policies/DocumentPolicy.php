<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;

/** REQ §10 documents, §11.2 verification. Students see and upload their own. */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return StudentPolicy::isOwner($user, $document->student)
            || ($user->can('documents.view') && StudentPolicy::inScope($user, $document->student));
    }

    public function upload(User $user, Student $student): bool
    {
        return StudentPolicy::isOwner($user, $student)
            || ($user->can('documents.manage') && StudentPolicy::inScope($user, $student));
    }

    public function review(User $user, Document $document): bool
    {
        return $user->can('documents.verify') && StudentPolicy::inScope($user, $document->student);
    }

    /** Only documents still awaiting review can be withdrawn. */
    public function delete(User $user, Document $document): bool
    {
        if ($document->status !== DocumentStatus::Pending || ! $document->is_current) {
            return false;
        }

        return ($document->uploaded_by === $user->id && StudentPolicy::isOwner($user, $document->student))
            || ($user->can('documents.manage') && StudentPolicy::inScope($user, $document->student));
    }
}
