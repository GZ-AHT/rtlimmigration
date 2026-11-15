<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentReviewed;
use App\Notifications\DocumentUploaded;
use App\Policies\StudentPolicy;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload, review and withdraw student documents (REQ §10, §11.2).
 *
 * Files live on a private disk under a random name; the original name is
 * kept only as metadata. Uploading to a category that already has a
 * document keeps the old one as history (version chain via replaces_id).
 */
class ManageDocuments
{
    public function upload(Student $student, UploadedFile $upload, User $actor, ?int $categoryId, ?int $fileId, ?string $title, ?CarbonInterface $expiresAt): Document
    {
        if ($fileId !== null && ! $student->files()->whereKey($fileId)->exists()) {
            throw ValidationException::withMessages(['student_file_id' => __('That file does not belong to this student.')]);
        }

        $disk = (string) config('documents.disk');
        $extension = strtolower($upload->guessExtension() ?: $upload->getClientOriginalExtension());
        $path = $upload->storeAs(
            config('documents.directory').'/'.$student->id,
            Str::uuid()->toString().'.'.$extension,
            $disk,
        );

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be saved. Please try again.')]);
        }

        try {
            $document = DB::transaction(function () use ($student, $upload, $actor, $categoryId, $fileId, $title, $expiresAt, $disk, $path) {
                $previous = $categoryId !== null
                    ? $student->documents()->where('category_id', $categoryId)->where('is_current', true)->lockForUpdate()->latest('id')->first()
                    : null;

                $previous?->update(['is_current' => false]);

                return $student->documents()->create([
                    'student_file_id' => $fileId,
                    'category_id' => $categoryId,
                    'title' => $title,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => mb_substr($upload->getClientOriginalName(), 0, 255),
                    'mime_type' => (string) $upload->getMimeType(),
                    'size' => (int) $upload->getSize(),
                    'status' => DocumentStatus::Pending,
                    'uploaded_by' => $actor->id,
                    'replaces_id' => $previous?->id,
                    'is_current' => true,
                    'expires_at' => $expiresAt,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        // A student's own upload goes to their counselor's review queue.
        if (StudentPolicy::isOwner($actor, $student) && $student->counselor) {
            $student->counselor->notify(new DocumentUploaded($document->load('student.user', 'category')));
        }

        return $document;
    }

    public function review(Document $document, User $actor, DocumentStatus $decision, ?string $reason): void
    {
        if ($decision === DocumentStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('Choose verify or reject.')]);
        }

        if ($decision === DocumentStatus::Rejected && blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => __('Tell the student why the document was rejected.')]);
        }

        if (! $document->is_current) {
            throw ValidationException::withMessages(['status' => __('Only the current version of a document can be reviewed.')]);
        }

        $document->update([
            'status' => $decision,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $decision === DocumentStatus::Rejected ? $reason : null,
        ]);

        $document->student->user->notify(new DocumentReviewed($document->load('category')));
    }

    /** Withdraw a pending document; the version it replaced becomes current again. */
    public function withdraw(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $document->update(['is_current' => false]);
            $document->delete();
            $document->previous?->update(['is_current' => true]);
        });
    }
}
