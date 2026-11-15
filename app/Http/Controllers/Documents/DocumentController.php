<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\ManageDocuments;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student documents (REQ §10, §11.2), shared by the admin, counselor and
 * student-portal areas. Files are only ever served through show(), after
 * an authorization check.
 */
class DocumentController extends Controller
{
    public function store(Request $request, Student $student, ManageDocuments $documents): RedirectResponse
    {
        Gate::authorize('upload', [Document::class, $student]);

        $data = self::validateUpload($request);

        $documents->upload(
            $student,
            $request->file('file'),
            $request->user(),
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['student_file_id']) ? (int) $data['student_file_id'] : null,
            $data['title'] ?? null,
            isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
        );

        $this->toast(['type' => 'success', 'message' => __('Document uploaded. It will be reviewed shortly.')]);

        return back();
    }

    /** View inline (PDF, images) or download (everything else). */
    public function show(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        $inline = ! $request->boolean('download') && in_array($document->mime_type, config('documents.inline'), true);
        $name = str_replace(['"', "\r", "\n"], '', $document->original_name);

        return $disk->response($document->path, $name, [
            'Content-Type' => $document->mime_type,
            // Never let the browser guess the type, and never run scripts
            // from an uploaded file.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function review(Request $request, Document $document, ManageDocuments $documents): RedirectResponse
    {
        Gate::authorize('review', $document);

        $data = $request->validate([
            'status' => ['required', Rule::in([DocumentStatus::Verified->value, DocumentStatus::Rejected->value])],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $documents->review($document, $request->user(), DocumentStatus::from($data['status']), $data['rejection_reason'] ?? null);

        $this->toast(['type' => 'success', 'message' => $data['status'] === 'verified' ? __('Document verified.') : __('Document rejected. The student has been told why.')]);

        return back();
    }

    public function destroy(Document $document, ManageDocuments $documents): RedirectResponse
    {
        Gate::authorize('delete', $document);
        // Holds for Super Admin too: reviewed documents are permanent records.
        abort_unless($document->status === DocumentStatus::Pending && $document->is_current, 403);

        $documents->withdraw($document);

        $this->toast(['type' => 'success', 'message' => __('Document withdrawn.')]);

        return back();
    }

    /** @return array{category_id?: int|string|null, student_file_id?: int|string|null, title?: string|null, expires_at?: string|null} */
    public static function validateUpload(Request $request): array
    {
        $allowed = config('documents.allowed');

        /** @var array{category_id?: int|string|null, student_file_id?: int|string|null, title?: string|null, expires_at?: string|null} */
        return $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.config('documents.max_size_kb'),
                'mimes:'.implode(',', array_keys($allowed)),
                'mimetypes:'.implode(',', array_unique(array_merge(...array_values($allowed)))),
            ],
            'category_id' => ['nullable', 'integer', Rule::exists(DocumentCategory::class, 'id')->where('is_active', true)],
            'student_file_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'required_without:category_id', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ], [
            'file.mimes' => __('Upload a PDF, JPG, PNG, WEBP, DOC or DOCX file.'),
            'file.mimetypes' => __('Upload a PDF, JPG, PNG, WEBP, DOC or DOCX file.'),
            'file.max' => __('The file must be :max MB or smaller.', ['max' => (int) (config('documents.max_size_kb') / 1024)]),
            'title.required_without' => __('Give the document a name, or choose a category.'),
        ]);
    }
}
