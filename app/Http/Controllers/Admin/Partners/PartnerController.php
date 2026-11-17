<?php

namespace App\Http\Controllers\Admin\Partners;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Models\PartnerDocument;
use App\Models\PartnerPayment;
use App\Models\Student;
use App\Support\Presenters\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** REQ §16 Partner Profile, Documents, Verification, Status, Commission, Payment History, Activity Logs. */
class PartnerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('partners.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Partner::STATUSES))],
        ]);

        $partners = Partner::query()
            ->with('country:id,name')
            ->withSum(['commissions as owed' => fn (Builder $q) => $q->whereIn('status', ['pending', 'approved'])], 'amount')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->where('organisation', 'like', '%'.addcslashes($s, '%_\\').'%')
                ->orWhere('partner_number', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->orderBy('organisation')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Partner $p) => [
                'id' => $p->id,
                'partner_number' => $p->partner_number,
                'organisation' => $p->organisation,
                'contact_name' => $p->contact_name,
                'country' => $p->country?->name,
                'type' => Partner::TYPES[$p->type] ?? $p->type,
                'status' => $p->status,
                'verified' => $p->verified_at !== null,
                'commission_rate' => $p->commission_rate,
                'owed' => number_format((float) $p->getAttribute('owed'), 2),
            ]);

        return Inertia::render('admin/partners/Index', [
            'partners' => $partners,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function show(Partner $partner): Response
    {
        Gate::authorize('partners.view');

        $partner->load(['country:id,name', 'documents', 'commissions.student.user:id,name', 'payments.recorder:id,name']);
        $commissionTotals = $partner->commissions->groupBy('status')->map(fn ($c) => $c->sum('amount'));
        $paid = $partner->payments->sum('amount');

        return Inertia::render('admin/partners/Show', [
            'partner' => [
                'id' => $partner->id,
                'partner_number' => $partner->partner_number,
                'organisation' => $partner->organisation,
                'contact_name' => $partner->contact_name,
                'email' => $partner->email,
                'phone' => $partner->phone ?? '',
                'country_id' => $partner->country_id ? (string) $partner->country_id : '',
                'country' => $partner->country?->name,
                'city' => $partner->city ?? '',
                'website' => $partner->website ?? '',
                'type' => $partner->type,
                'commission_rate' => $partner->commission_rate ?? '',
                'status' => $partner->status,
                'verified_at' => $partner->verified_at?->toIso8601String(),
                'notes' => $partner->notes ?? '',
                'created_at' => $partner->created_at?->toIso8601String(),
            ],
            'documents' => $partner->documents->sortByDesc('id')->values()->map(fn (PartnerDocument $d) => self::presentDocument($d)),
            'commissions' => $partner->commissions->sortByDesc('id')->values()->map(fn (PartnerCommission $c) => [
                'id' => $c->id,
                'description' => $c->description,
                'student' => $c->student?->user?->name,
                'amount' => $c->amount,
                'currency' => $c->currency,
                'status' => $c->status,
                'status_label' => PartnerCommission::STATUSES[$c->status][0] ?? $c->status,
                'status_tone' => PartnerCommission::STATUSES[$c->status][1] ?? 'neutral',
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'payments' => $partner->payments->sortByDesc('paid_on')->values()->map(fn (PartnerPayment $p) => [
                'id' => $p->id, 'amount' => $p->amount, 'currency' => $p->currency, 'method' => $p->method,
                'reference' => $p->reference, 'paid_on' => $p->paid_on->toDateString(), 'note' => $p->note, 'recorder' => $p->recorder?->name,
            ]),
            'totals' => [
                'owed' => number_format((float) ($commissionTotals['pending'] ?? 0) + (float) ($commissionTotals['approved'] ?? 0), 2),
                'earned' => number_format((float) $partner->commissions->whereIn('status', ['pending', 'approved', 'paid'])->sum('amount'), 2),
                'paid' => number_format((float) $paid, 2),
            ],
            'activity' => Activity::query()->where('log_name', 'partners')->whereMorphedTo('subject', $partner)->with('causer:id,name')->latest('id')->limit(20)->get()
                ->map(fn (Activity $a) => ActivityPresenter::present($a)),
            'options' => [
                'countries' => Country::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
                'types' => collect(Partner::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'commissionStatuses' => collect(PartnerCommission::STATUSES)->map(fn ($s, $v) => ['value' => $v, 'label' => $s[0]])->values(),
            ],
            'canManage' => request()->user()->can('partners.manage'),
        ]);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $data = $request->validate([
            'organisation' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Partner::TYPES))],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $partner->update($data);
        activity('partners')->causedBy($request->user())->performedOn($partner)->log('Partner profile updated');
        $this->toast(['type' => 'success', 'message' => __('Partner saved.')]);

        return back();
    }

    public function status(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Partner::STATUSES))],
            'verified' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('status', $data)) {
            $partner->update(['status' => $data['status']]);
            activity('partners')->causedBy($request->user())->performedOn($partner)->log($data['status'] === 'active' ? 'Partner activated' : 'Partner suspended');
        }

        if (array_key_exists('verified', $data)) {
            $partner->update($data['verified'] ? ['verified_at' => now(), 'verified_by' => $request->user()->id] : ['verified_at' => null, 'verified_by' => null]);
            activity('partners')->causedBy($request->user())->performedOn($partner)->log($data['verified'] ? 'Partner verified' : 'Partner verification removed');
        }

        $this->toast(['type' => 'success', 'message' => __('Partner updated.')]);

        return back();
    }

    public function storeDocument(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $allowed = array_keys(config('documents.allowed'));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:'.config('documents.max_size_kb'), 'mimes:'.implode(',', $allowed)],
        ]);

        $file = $data['file'];
        $disk = (string) config('documents.disk');
        $path = $file->storeAs('partner-documents/'.$partner->id, Str::random(40).'.'.$file->extension(), $disk);

        $partner->documents()->create([
            'title' => $data['title'],
            'disk' => $disk,
            'path' => (string) $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        activity('partners')->causedBy($request->user())->performedOn($partner)->log('Partner document uploaded');
        $this->toast(['type' => 'success', 'message' => __('Document uploaded.')]);

        return back();
    }

    public function document(PartnerDocument $document): StreamedResponse
    {
        Gate::authorize('partners.view');

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroyDocument(Request $request, PartnerDocument $document): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $document->delete();
        $this->toast(['type' => 'success', 'message' => __('Document deleted.')]);

        return back();
    }

    public function storeCommission(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:200'],
            'student_id' => ['nullable', 'integer', Rule::exists(Student::class, 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ]);

        $partner->commissions()->create([...$data, 'currency' => strtoupper($data['currency']), 'status' => 'pending', 'created_by' => $request->user()->id]);
        activity('partners')->causedBy($request->user())->performedOn($partner)->withProperties(['amount' => $data['amount']])->log('Commission added');
        $this->toast(['type' => 'success', 'message' => __('Commission added.')]);

        return back();
    }

    public function updateCommission(Request $request, PartnerCommission $commission): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(PartnerCommission::STATUSES))]]);
        $commission->update($data);
        activity('partners')->causedBy($request->user())->performedOn(Partner::findOrFail($commission->partner_id))->withProperties(['commission' => $commission->id, 'status' => $data['status']])->log('Commission status changed');
        $this->toast(['type' => 'success', 'message' => __('Commission updated.')]);

        return back();
    }

    public function storePayment(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('partners.manage');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $partner->payments()->create([...$data, 'currency' => strtoupper($data['currency']), 'recorded_by' => $request->user()->id]);
        activity('partners')->causedBy($request->user())->performedOn($partner)->withProperties(['amount' => $data['amount']])->log('Payment to partner recorded');
        $this->toast(['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /** @return array{id: int, title: string, name: string, size: int, url: string, created_at: string|null} */
    public static function presentDocument(PartnerDocument $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'name' => $d->original_name,
            'size' => $d->size,
            'url' => route('admin.partners.documents.show', $d, false),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
