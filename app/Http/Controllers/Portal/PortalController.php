<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Documents\ManageDocuments;
use App\Actions\Students\UpdateStudentProfile;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Requests\Students\StudentProfileRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use App\Models\StudentFile;
use App\Support\StudentRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own profile, file and documents (REQ §10.1 "My Profile",
 * "My File", "Documents"). Everything is scoped to the signed-in student.
 */
class PortalController extends Controller
{
    public function profile(Request $request): Response
    {
        $student = $this->student($request)->load(['user', 'profile', 'educations']);

        return Inertia::render('portal/Profile', [
            'profile' => StudentRecords::profileForm($student),
            'completeness' => StudentRecords::completeness($student),
            'options' => StudentRecords::profileOptions(),
        ]);
    }

    public function updateProfile(StudentProfileRequest $request, UpdateStudentProfile $update): RedirectResponse
    {
        $student = $this->student($request);
        $update($student, $request->validated('phone'), $request->profile(), $request->educations());

        $this->toast(['type' => 'success', 'message' => __('Your profile has been saved.')]);

        return to_route('portal.profile.edit');
    }

    public function file(Request $request): Response
    {
        $student = $this->student($request)->load(['user', 'counselor.counselorProfile', 'profile', 'educations', 'files.service', 'files.country']);
        $checklist = $this->checklist($request, $student);

        return Inertia::render('portal/File', [
            'student' => [
                'name' => $student->user->name,
                'student_number' => $student->student_number,
            ],
            'counselor' => $student->counselor ? [
                'name' => $student->counselor->name,
                'email' => $student->counselor->email,
                'phone' => $student->counselor->counselorProfile?->phone,
                'designation' => $student->counselor->counselorProfile?->designation,
            ] : null,
            'files' => $student->files->map(fn (StudentFile $f) => StudentRecords::fileRow($f))->values(),
            'completeness' => StudentRecords::completeness($student),
            'documents' => ['required' => $checklist['required'], 'verified' => $checklist['verified']],
        ]);
    }

    public function documents(Request $request): Response
    {
        $student = $this->student($request)->load('files');

        return Inertia::render('portal/Documents', [
            'checklist' => $this->checklist($request, $student),
            'files' => $student->files->map(fn (StudentFile $f) => ['value' => (string) $f->id, 'label' => $f->file_number])->values(),
            'categories' => DocumentCategory::active()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'limits' => self::limits(),
        ]);
    }

    public function uploadDocument(Request $request, ManageDocuments $documents): RedirectResponse
    {
        $student = $this->student($request);
        $data = DocumentController::validateUpload($request);

        $documents->upload(
            $student,
            $request->file('file'),
            $request->user(),
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['student_file_id']) ? (int) $data['student_file_id'] : null,
            $data['title'] ?? null,
            isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
        );

        $this->toast(['type' => 'success', 'message' => __('Thank you. Your document has been uploaded for review.')]);

        return back();
    }

    /** @return array{max_mb: int, extensions: list<string>} */
    public static function limits(): array
    {
        return [
            'max_mb' => (int) floor(config('documents.max_size_kb') / 1024),
            'extensions' => array_map('strval', array_keys(config('documents.allowed'))),
        ];
    }

    /** @return array{items: list<array<string, mixed>>, required: int, verified: int} */
    private function checklist(Request $request, Student $student): array
    {
        return StudentRecords::documentChecklist($student, $request->user(), fn (Document $d) => [
            'view_url' => route('portal.documents.show', $d, false),
            'download_url' => route('portal.documents.show', ['document' => $d, 'download' => 1], false),
            'delete_url' => route('portal.documents.destroy', $d, false),
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;

        abort_if($student === null, 403, __('No student record is linked to your account yet. Please contact your counselor.'));

        return $student;
    }
}
