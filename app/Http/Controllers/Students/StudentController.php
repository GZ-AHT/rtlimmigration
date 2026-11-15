<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\AssignStudentCounselor;
use App\Actions\Students\CreateStudent;
use App\Concerns\ProfileValidationRules;
use App\Enums\StudentSource;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\PortalController;
use App\Models\Country;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\PaymentRequest;
use App\Models\Service;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use App\Support\CounselorDirectory;
use App\Support\Presenters\PaymentPresenter;
use App\Support\Presenters\StudentPresenter;
use App\Support\StudentRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student management (REQ §10, §11.2). Mounted under /admin and
 * /counselor ("Assigned Students", "Create Student").
 */
class StudentController extends Controller
{
    use ProfileValidationRules, ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        return $this->listing($request, profiles: false);
    }

    /** REQ §8.1 "Student Profile Update": students ordered by how incomplete their profile is. */
    public function profiles(Request $request): Response
    {
        return $this->listing($request, profiles: true);
    }

    private function listing(Request $request, bool $profiles): Response
    {
        Gate::authorize('viewAny', Student::class);

        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'counselor' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::enum(StudentSource::class)],
            'sort' => ['nullable', Rule::in(['created_at', 'student_number'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $students = Student::query()
            ->visibleTo($user)
            ->with(['user' => fn ($q) => $q->withTrashed(), 'counselor:id,name', 'profile', 'educations'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('student_number', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->withTrashed()->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['counselor'] ?? null, fn (Builder $q, string $c) => $c === 'unassigned' ? $q->whereNull('counselor_id') : $q->where('counselor_id', (int) $c))
            ->when($filters['source'] ?? null, fn (Builder $q, string $s) => $q->where('source', $s))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Student $s) => [
                ...StudentPresenter::row($s),
                'completeness' => StudentRecords::completeness($s)['percent'],
                'url' => $profiles ? $this->areaRoute($request, 'students.profile.edit', $s) : $this->areaRoute($request, 'students.show', $s),
            ]);

        if ($profiles) {
            $students->setCollection($students->getCollection()->sortBy('completeness')->values());
        }

        return Inertia::render('students/Index', [
            'mode' => $profiles ? 'profiles' : 'list',
            'students' => $students,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'counselor' => $filters['counselor'] ?? '',
                'source' => $filters['source'] ?? '',
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
            'sources' => StudentSource::options(),
            'counselors' => $user->can('students.assign-counselor') ? app(CounselorDirectory::class)->options() : [],
            'canSeeAll' => $user->can('students.assign-counselor'),
            'urls' => [
                'create' => $user->can('create', Student::class) && $this->areaHas($request, 'students.create') ? $this->areaRoute($request, 'students.create') : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        $user = $request->user();

        return Inertia::render('students/Form', [
            'counselors' => $user->can('students.assign-counselor') ? app(CounselorDirectory::class)->options() : [],
            // Counselors creating a student become its counselor (REQ §11 step 4).
            'assignsSelf' => ! $user->can('students.assign-counselor'),
            'urls' => ['submit' => $this->areaRoute($request, 'students.store'), 'cancel' => $this->areaRoute($request, 'students.index')],
        ]);
    }

    public function store(Request $request, CreateStudent $create): RedirectResponse
    {
        Gate::authorize('create', Student::class);

        $user = $request->user();
        $data = $request->validate([
            ...$this->profileRules(),
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'counselor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'send_invitation' => ['sometimes', 'boolean'],
        ]);

        if (! $user->can('students.assign-counselor')) {
            $data['counselor_id'] = $user->id;
        }

        $student = $create($data, $user->can('students.assign-counselor') ? StudentSource::Admin : StudentSource::Counselor, $user);

        $this->toast(['type' => 'success', 'message' => __('Student :number created.', ['number' => $student->student_number])
            .(($data['send_invitation'] ?? true) ? ' '.__('An invitation email has been sent.') : '')]);

        return redirect($this->areaRoute($request, 'students.show', $student));
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load([
            'user' => fn ($q) => $q->withTrashed(), 'counselor', 'lead', 'creator',
            'counselorAssignments.counselor', 'counselorAssignments.assigner',
            'profile', 'educations', 'files.service', 'files.country', 'notes.user', 'paymentRequests.type', 'paymentRequests.file',
        ]);
        $user = $request->user();
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['overview', 'profile', 'files', 'documents', 'payments', 'notes'])]])['tab'] ?? 'overview';

        return Inertia::render('students/Show', [
            'tab' => $tab,
            'student' => StudentPresenter::detail($student, $user),
            'counselors' => $user->can('assignCounselor', $student) ? app(CounselorDirectory::class)->options() : [],
            'completeness' => StudentRecords::completeness($student),
            'profile' => StudentRecords::profileSummary($student),
            'files' => $student->files->map(fn (StudentFile $f) => [...StudentRecords::fileRow($f), 'url' => $this->areaRoute($request, 'files.show', $f)])->values(),
            'checklist' => StudentRecords::documentChecklist($student, $user, fn (Document $d) => [
                'view_url' => $this->areaRoute($request, 'documents.show', $d),
                'download_url' => $this->areaRoute($request, 'documents.show', ['document' => $d, 'download' => 1]),
                'review_url' => $this->areaRoute($request, 'documents.review', $d),
                'delete_url' => $this->areaRoute($request, 'documents.destroy', $d),
            ]),
            // REQ §10 "Payment History", "Student Fees", "Outstanding Balance".
            'payments' => $user->can('payments.view') ? [
                'totals' => PaymentPresenter::totals($student->paymentRequests),
                'requests' => $student->paymentRequests->map(fn (PaymentRequest $r) => [...PaymentPresenter::request($r), 'url' => $this->areaRoute($request, 'payment-requests.show', $r)])->values(),
                'create_url' => $user->can('create', [PaymentRequest::class, $student]) && $this->areaHas($request, 'payment-requests.create') ? $this->areaRoute($request, 'payment-requests.create', ['student' => $student->id]) : null,
            ] : null,
            // Notes are internal: staff only.
            'notes' => $student->notes->map(fn ($n) => [
                'id' => $n->id, 'body' => $n->body, 'author' => $n->user?->name, 'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'fileOptions' => [
                'services' => Service::active()->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->values(),
                'countries' => Country::destinations()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            ],
            'documentOptions' => [
                'categories' => DocumentCategory::active()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
                'files' => $student->files->map(fn (StudentFile $f) => ['value' => (string) $f->id, 'label' => $f->file_number])->values(),
                'limits' => PortalController::limits(),
            ],
            'can' => [
                'update_profile' => $user->can('updateProfile', $student),
                'open_file' => $user->can('open', [StudentFile::class, $student]),
                'upload' => $user->can('upload', [Document::class, $student]),
                'add_note' => $user->can('addNote', $student),
            ],
            'urls' => [
                'index' => $this->areaRoute($request, 'students.index'),
                'self' => $this->areaRoute($request, 'students.show', $student),
                'assign' => $this->areaHas($request, 'students.assign') ? $this->areaRoute($request, 'students.assign', $student) : null,
                'lead' => $student->lead && $user->can('view', $student->lead) ? $this->areaRoute($request, 'leads.show', $student->lead) : null,
                'user' => $user->can('view', $student->user) && Route::has('admin.users.show') ? route('admin.users.show', $student->user_id, false) : null,
                'profile' => $this->areaRoute($request, 'students.profile.edit', $student),
                'openFile' => $this->areaRoute($request, 'students.files.store', $student),
                'upload' => $this->areaRoute($request, 'students.documents.store', $student),
                'notes' => $this->areaRoute($request, 'students.notes.store', $student),
            ],
        ]);
    }

    public function assign(Request $request, Student $student, AssignStudentCounselor $assign): RedirectResponse
    {
        Gate::authorize('assignCounselor', $student);

        $data = $request->validate([
            'counselor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $counselor = isset($data['counselor_id']) ? User::findOrFail((int) $data['counselor_id']) : null;
        $assign($student, $counselor, $request->user(), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => $counselor ? __('Assigned to :name.', ['name' => $counselor->name]) : __('Counselor removed.')]);

        return back();
    }
}
