<?php

namespace App\Http\Controllers\Students;

use App\Actions\Files\ManageStudentFile;
use App\Enums\FileStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Service;
use App\Models\Student;
use App\Models\StudentFile;
use App\Support\Presenters\ActivityPresenter;
use App\Support\StudentRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** REQ §11.2 student files: open, list, details, status. Admin and counselor areas. */
class StudentFileController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StudentFile::class);

        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(FileStatus::class)],
            'service' => ['nullable', 'integer'],
            'country' => ['nullable', 'integer'],
        ]);

        $files = StudentFile::query()
            ->visibleTo($user)
            ->with(['student.user', 'service:id,name', 'country:id,name'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('file_number', 'like', $like)
                    ->orWhereHas('student', fn (Builder $st) => $st->where('student_number', 'like', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['service'] ?? null, fn (Builder $q, int|string $s) => $q->where('service_id', $s))
            ->when($filters['country'] ?? null, fn (Builder $q, int|string $c) => $q->where('country_id', $c))
            ->latest('opened_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentFile $f) => [...StudentRecords::fileRow($f), 'url' => $this->areaRoute($request, 'files.show', $f)]);

        return Inertia::render('files/Index', [
            'files' => $files,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'service' => (string) ($filters['service'] ?? ''),
                'country' => (string) ($filters['country'] ?? ''),
            ],
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request, Student $student, ManageStudentFile $manage): RedirectResponse
    {
        Gate::authorize('open', [StudentFile::class, $student]);

        $file = $manage->open($student, $this->validated($request), $request->user());

        $this->toast(['type' => 'success', 'message' => __('File :number opened.', ['number' => $file->file_number])]);

        return redirect($this->areaRoute($request, 'files.show', $file));
    }

    public function show(Request $request, StudentFile $file): Response
    {
        Gate::authorize('view', $file);

        $file->load(['student.user', 'student.counselor', 'service', 'country', 'opener', 'documents' => fn ($q) => $q->where('is_current', true)->with('category')]);
        $user = $request->user();

        return Inertia::render('files/Show', [
            'file' => [
                ...StudentRecords::fileRow($file),
                'service_id' => $file->service_id,
                'country_id' => $file->country_id,
                'notes' => $file->notes,
                'close_reason' => $file->close_reason,
                'opened_by' => $file->opener?->name,
                'counselor' => $file->student->counselor?->name,
                'student' => [
                    'id' => $file->student_id,
                    'name' => $file->student->user->name,
                    'number' => $file->student->student_number,
                    'email' => $file->student->user->email,
                    'url' => $this->areaRoute($request, 'students.show', $file->student),
                ],
                'documents' => $file->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->displayName(),
                    'status_label' => $d->status->label(),
                    'status_tone' => $d->status->tone(),
                    'view_url' => $this->areaRoute($request, 'documents.show', $d),
                ])->values(),
                'can' => ['update' => $user->can('update', $file)],
            ],
            'activity' => Activity::query()->whereMorphedTo('subject', $file)->with('causer:id,name')->latest()->limit(20)->get()
                ->map(fn (Activity $a) => ActivityPresenter::present($a)),
            'options' => $this->options(),
            'urls' => [
                'index' => $this->areaRoute($request, 'files.index'),
                'update' => $this->areaRoute($request, 'files.update', $file),
                'status' => $this->areaRoute($request, 'files.status', $file),
                'documents' => $this->areaRoute($request, 'students.show', ['student' => $file->student_id, 'tab' => 'documents']),
            ],
        ]);
    }

    public function update(Request $request, StudentFile $file, ManageStudentFile $manage): RedirectResponse
    {
        Gate::authorize('update', $file);

        $manage->update($file, $this->validated($request));

        $this->toast(['type' => 'success', 'message' => __('File updated.')]);

        return back();
    }

    public function status(Request $request, StudentFile $file, ManageStudentFile $manage): RedirectResponse
    {
        Gate::authorize('update', $file);

        $data = $request->validate([
            'status' => ['required', Rule::enum(FileStatus::class)],
            'close_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $manage->changeStatus($file, FileStatus::from($data['status']), $data['close_reason'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('File status updated.')]);

        return back();
    }

    /** @return array{service_id?: int|null, country_id?: int|null, intake?: string|null, notes?: string|null} */
    private function validated(Request $request): array
    {
        /** @var array{service_id?: int|null, country_id?: int|null, intake?: string|null, notes?: string|null} */
        return $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists(Service::class, 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'intake' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => StudentRecords::fileStatusOptions(),
            'services' => Service::active()->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->values(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
        ];
    }
}
