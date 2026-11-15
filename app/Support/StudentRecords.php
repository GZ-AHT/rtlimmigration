<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\EducationLevel;
use App\Enums\FileStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentFile;
use App\Models\User;
use App\Policies\DocumentPolicy;

/**
 * Shapes a student's profile, files and documents for the staff and
 * student-portal screens, and works out profile completeness and the
 * document checklist (REQ §10, §10.1, §11.2).
 */
class StudentRecords
{
    /**
     * Fields a complete profile needs, with labels for "missing" hints.
     *
     * @var array<string, string>
     */
    private const REQUIRED_PROFILE_FIELDS = [
        'date_of_birth' => 'Date of birth',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'address_line' => 'Address',
        'city' => 'City',
        'country' => 'Country',
        'emergency_contact_name' => 'Emergency contact',
        'emergency_contact_phone' => 'Emergency contact phone',
        'passport_number' => 'Passport number',
        'passport_expiry_date' => 'Passport expiry date',
    ];

    /** @return array{percent: int, missing: list<string>} */
    public static function completeness(Student $student): array
    {
        $profile = $student->profile;
        $missing = [];

        foreach (self::REQUIRED_PROFILE_FIELDS as $field => $label) {
            if (blank($profile?->getAttribute($field))) {
                $missing[] = $label;
            }
        }

        if (blank($student->phone)) {
            $missing[] = 'Phone number';
        }

        if ($student->educations->isEmpty()) {
            $missing[] = 'Education history';
        }

        $total = count(self::REQUIRED_PROFILE_FIELDS) + 2;

        return ['percent' => (int) round(($total - count($missing)) / $total * 100), 'missing' => $missing];
    }

    /** @return array<string, mixed> editable profile values (passport number included). */
    public static function profileForm(Student $student): array
    {
        $p = $student->profile;

        return [
            'phone' => $student->phone,
            'date_of_birth' => $p?->date_of_birth?->toDateString(),
            'gender' => $p?->gender?->value,
            'marital_status' => $p?->marital_status?->value,
            'nationality' => $p?->nationality,
            'place_of_birth' => $p?->place_of_birth,
            'alternate_phone' => $p?->alternate_phone,
            'address_line' => $p?->address_line,
            'city' => $p?->city,
            'state' => $p?->state,
            'postal_code' => $p?->postal_code,
            'country' => $p?->country,
            'emergency_contact_name' => $p?->emergency_contact_name,
            'emergency_contact_relation' => $p?->emergency_contact_relation,
            'emergency_contact_phone' => $p?->emergency_contact_phone,
            'passport_number' => $p?->passport_number,
            'passport_issue_date' => $p?->passport_issue_date?->toDateString(),
            'passport_expiry_date' => $p?->passport_expiry_date?->toDateString(),
            'passport_issue_place' => $p?->passport_issue_place,
            'passport_issuing_country' => $p?->passport_issuing_country,
            'educations' => $student->educations->map(fn (StudentEducation $e) => [
                'id' => $e->id,
                'level' => $e->level->value,
                'institution' => $e->institution,
                'board_or_university' => $e->board_or_university,
                'country' => $e->country,
                'major' => $e->major,
                'result' => $e->result,
                'passing_year' => $e->passing_year,
            ])->values(),
        ];
    }

    /**
     * Read-only summary for the profile tab. The passport number is masked.
     *
     * @return array<string, mixed>
     */
    public static function profileSummary(Student $student): array
    {
        $p = $student->profile;
        $expiry = $p?->passport_expiry_date;

        return [
            'personal' => [
                ['label' => 'Date of birth', 'value' => $p?->date_of_birth?->format('d M Y')],
                ['label' => 'Gender', 'value' => $p?->gender?->label()],
                ['label' => 'Marital status', 'value' => $p?->marital_status?->label()],
                ['label' => 'Nationality', 'value' => $p?->nationality],
                ['label' => 'Place of birth', 'value' => $p?->place_of_birth],
            ],
            'contact' => [
                ['label' => 'Phone', 'value' => $student->phone],
                ['label' => 'Alternate phone', 'value' => $p?->alternate_phone],
                ['label' => 'Address', 'value' => collect([$p?->address_line, $p?->city, $p?->state, $p?->postal_code, $p?->country])->filter()->implode(', ') ?: null],
                ['label' => 'Emergency contact', 'value' => $p?->emergency_contact_name ? trim("{$p->emergency_contact_name} ({$p->emergency_contact_relation}) {$p->emergency_contact_phone}") : null],
            ],
            'passport' => [
                ['label' => 'Passport number', 'value' => $p?->maskedPassportNumber()],
                ['label' => 'Issuing country', 'value' => $p?->passport_issuing_country],
                ['label' => 'Issued', 'value' => $p?->passport_issue_date?->format('d M Y')],
                ['label' => 'Expires', 'value' => $expiry?->format('d M Y')],
                ['label' => 'Place of issue', 'value' => $p?->passport_issue_place],
            ],
            // Many destinations need 6+ months of passport validity.
            'passport_warning' => match (true) {
                $expiry === null => null,
                $expiry->isPast() => 'expired',
                $expiry->lessThan(now()->addMonths(6)) => 'expiring',
                default => null,
            },
            'educations' => $student->educations->map(fn (StudentEducation $e) => [
                'id' => $e->id,
                'level' => $e->level->label(),
                'institution' => $e->institution,
                'board_or_university' => $e->board_or_university,
                'major' => $e->major,
                'result' => $e->result,
                'passing_year' => $e->passing_year,
                'country' => $e->country,
            ])->values(),
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    public static function profileOptions(): array
    {
        return [
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'educationLevels' => EducationLevel::options(),
        ];
    }

    /**
     * One row per active category with its current document, plus any
     * uncategorised documents. Required categories make up the checklist.
     *
     * @param  callable(Document): array<string, string|null>  $urls
     * @return array{items: list<array<string, mixed>>, required: int, verified: int}
     */
    public static function documentChecklist(Student $student, User $viewer, callable $urls): array
    {
        $current = $student->documents()->where('is_current', true)->with(['category', 'uploader', 'reviewer', 'file'])->latest('id')->get();
        $history = $student->documents()->withTrashed()->where('is_current', false)->with('uploader')->latest('id')->get()->groupBy('category_id');
        $policy = new DocumentPolicy;

        $present = fn (Document $d) => [
            'id' => $d->id,
            'name' => $d->displayName(),
            'original_name' => $d->original_name,
            'size' => $d->size,
            'mime_type' => $d->mime_type,
            'status' => $d->status->value,
            'status_label' => $d->status->label(),
            'status_tone' => $d->status->tone(),
            'rejection_reason' => $d->rejection_reason,
            'file_number' => $d->file?->file_number,
            'expires_at' => $d->expires_at?->toDateString(),
            'uploaded_by' => $d->uploader?->name,
            'uploaded_at' => $d->created_at?->toIso8601String(),
            'reviewed_by' => $d->reviewer?->name,
            'reviewed_at' => $d->reviewed_at?->toIso8601String(),
            'can' => [
                'review' => $viewer->can('review', $d),
                'delete' => $policy->delete($viewer, $d) || $viewer->isSuperAdmin() && $d->status === DocumentStatus::Pending && $d->is_current,
            ],
            ...$urls($d),
        ];

        $items = [];
        $required = 0;
        $verified = 0;

        foreach (DocumentCategory::active()->get() as $category) {
            $doc = $current->firstWhere('category_id', $category->id);

            if ($category->is_required) {
                $required++;
                $verified += $doc?->status === DocumentStatus::Verified ? 1 : 0;
            }

            $items[] = [
                'category_id' => $category->id,
                'category' => $category->name,
                'description' => $category->description,
                'required' => $category->is_required,
                'document' => $doc ? $present($doc) : null,
                'history' => ($history[$category->id] ?? collect())->map(fn (Document $d) => [
                    'id' => $d->id,
                    'original_name' => $d->original_name,
                    'status_label' => $d->trashed() ? 'Withdrawn' : $d->status->label(),
                    'uploaded_at' => $d->created_at?->toIso8601String(),
                    'uploaded_by' => $d->uploader?->name,
                    ...($d->trashed() ? [] : $urls($d)),
                ])->values(),
            ];
        }

        foreach ($current->whereNull('category_id') as $doc) {
            $items[] = ['category_id' => null, 'category' => $doc->displayName(), 'description' => null, 'required' => false, 'document' => $present($doc), 'history' => []];
        }

        return ['items' => $items, 'required' => $required, 'verified' => $verified];
    }

    /** @return array<string, mixed> */
    public static function fileRow(StudentFile $file): array
    {
        return [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'student' => $file->relationLoaded('student') ? $file->student->user->name : null,
            'student_number' => $file->relationLoaded('student') ? $file->student->student_number : null,
            'service' => $file->service?->name,
            'country' => $file->country?->name,
            'intake' => $file->intake,
            'status' => $file->status->value,
            'status_label' => $file->status->label(),
            'status_tone' => $file->status->tone(),
            'opened_at' => $file->opened_at->toIso8601String(),
            'closed_at' => $file->closed_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function fileStatusOptions(): array
    {
        return FileStatus::options();
    }
}
