<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Documents waiting for verification (REQ §8.1 "Student Documents", §11.2). */
class DocumentQueueController extends Controller
{
    use ResolvesAreaRoutes;

    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Document::class);

        $user = $request->user();
        $status = $request->validate(['status' => ['nullable', Rule::enum(DocumentStatus::class)]])['status'] ?? DocumentStatus::Pending->value;

        $documents = Document::query()
            ->where('is_current', true)
            ->where('status', $status)
            ->whereHas('student', fn (Builder $q) => $q->visibleTo($user))
            ->with(['student.user', 'category', 'uploader'])
            ->oldest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Document $d) => [
                'id' => $d->id,
                'name' => $d->displayName(),
                'original_name' => $d->original_name,
                'student' => $d->student->user->name,
                'student_number' => $d->student->student_number,
                'uploaded_by' => $d->uploader?->name,
                'uploaded_at' => $d->created_at?->toIso8601String(),
                'status_label' => $d->status->label(),
                'status_tone' => $d->status->tone(),
                'rejection_reason' => $d->rejection_reason,
                'can_review' => $user->can('review', $d),
                'view_url' => $this->areaRoute($request, 'documents.show', $d),
                'review_url' => $this->areaRoute($request, 'documents.review', $d),
                'student_url' => $this->areaRoute($request, 'students.show', ['student' => $d->student_id, 'tab' => 'documents']),
            ]);

        return Inertia::render('documents/Queue', [
            'documents' => $documents,
            'status' => $status,
            'statuses' => DocumentStatus::options(),
        ]);
    }
}
