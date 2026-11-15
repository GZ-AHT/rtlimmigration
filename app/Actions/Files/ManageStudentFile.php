<?php

namespace App\Actions\Files;

use App\Enums\FileStatus;
use App\Events\StudentFileOpened;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use App\Notifications\StudentFileOpenedNotification;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** REQ §11.2 "File opening by counselor", "Unique file number", "File status". */
class ManageStudentFile
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * @param  array{service_id?: int|null, country_id?: int|null, intake?: string|null, notes?: string|null}  $data
     */
    public function open(Student $student, array $data, User $actor): StudentFile
    {
        $file = DB::transaction(fn () => $student->files()->create([
            'file_number' => $this->numbers->next('file'),
            'service_id' => $data['service_id'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'intake' => $data['intake'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => FileStatus::Open,
            'opened_by' => $actor->id,
            'opened_at' => now(),
        ]));

        StudentFileOpened::dispatch($file, $actor);

        $file->load(['service', 'country']);
        $student->user->notify(new StudentFileOpenedNotification($file));

        return $file;
    }

    /**
     * @param  array{service_id?: int|null, country_id?: int|null, intake?: string|null, notes?: string|null}  $data
     */
    public function update(StudentFile $file, array $data): void
    {
        $file->update($data);
    }

    public function changeStatus(StudentFile $file, FileStatus $status, ?string $reason): void
    {
        if ($status === FileStatus::Closed && blank($reason)) {
            throw ValidationException::withMessages(['close_reason' => __('Give a reason for closing the file.')]);
        }

        $file->update([
            'status' => $status,
            'closed_at' => $status->isActive() ? null : ($file->closed_at ?? now()),
            'close_reason' => $status === FileStatus::Closed ? $reason : null,
        ]);
    }
}
