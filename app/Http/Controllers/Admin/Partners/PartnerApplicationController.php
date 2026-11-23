<?php

namespace App\Http\Controllers\Admin\Partners;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\PartnerDocument;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §16: All / New / Pending applications, details, approve, reject. */
class PartnerApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('partners.view');

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PartnerApplication::STATUSES))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $applications = PartnerApplication::query()
            ->with('country:id,name')
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->where('organisation', 'like', '%'.addcslashes($s, '%_\\').'%')
                ->orWhere('contact_name', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PartnerApplication $a) => self::row($a));

        return Inertia::render('admin/partners/Applications', [
            'applications' => $applications,
            'filters' => ['status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''],
            'counts' => PartnerApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => collect(PartnerApplication::STATUSES)->map(fn ($s, $k) => ['value' => $k, 'label' => $s[0]])->values(),
        ]);
    }

    public function show(PartnerApplication $application): Response
    {
        Gate::authorize('partners.view');

        $application->load(['country:id,name', 'reviewer:id,name', 'documents', 'partner:id,organisation']);

        return Inertia::render('admin/partners/ApplicationShow', [
            'application' => [
                ...self::row($application),
                'phone' => $application->phone,
                'city' => $application->city,
                'website' => $application->website,
                'students_per_year' => $application->students_per_year,
                'job_title' => $application->job_title,
                'partnership_interest' => PartnerApplication::INTERESTS[$application->partnership_interest ?? ''] ?? null,
                'years_in_operation' => PartnerApplication::YEARS[$application->years_in_operation ?? ''] ?? null,
                'destinations' => Country::query()->whereKey($application->destinations ?? [])->orderBy('name')->pluck('name')->values(),
                'message' => $application->message,
                'reviewer' => $application->reviewer?->name,
                'reviewed_at' => $application->reviewed_at?->toIso8601String(),
                'review_note' => $application->review_note,
                'partner' => $application->partner ? ['id' => $application->partner->id, 'organisation' => $application->partner->organisation] : null,
                'documents' => $application->documents->map(fn (PartnerDocument $d) => PartnerController::presentDocument($d))->values(),
            ],
            'canApprove' => request()->user()->can('partners.approve'),
        ]);
    }

    public function approve(Request $request, PartnerApplication $application, NumberGenerator $numbers): RedirectResponse
    {
        Gate::authorize('partners.approve');
        $this->ensurePending($application);

        $data = $request->validate([
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $partner = DB::transaction(function () use ($application, $data, $request, $numbers) {
            $partner = Partner::create([
                'partner_number' => $numbers->next('partner'),
                'organisation' => $application->organisation,
                'contact_name' => $application->contact_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'country_id' => $application->country_id,
                'city' => $application->city,
                'website' => $application->website,
                'type' => $application->type,
                'commission_rate' => $data['commission_rate'] ?? null,
                'status' => 'active',
            ]);

            $application->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null, 'partner_id' => $partner->id]);
            $application->documents()->update(['partner_id' => $partner->id]);

            return $partner;
        });

        activity('partners')->causedBy($request->user())->performedOn($partner)->log('Partner application approved');
        $this->toast(['type' => 'success', 'message' => __(':name is now partner :number.', ['name' => $partner->organisation, 'number' => $partner->partner_number])]);

        return redirect()->route('admin.partners.show', $partner);
    }

    public function reject(Request $request, PartnerApplication $application): RedirectResponse
    {
        Gate::authorize('partners.approve');
        $this->ensurePending($application);

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']], ['note.required' => __('Give a reason so the team knows why.')]);
        $application->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note']]);

        activity('partners')->causedBy($request->user())->performedOn($application)->log('Partner application rejected');
        $this->toast(['type' => 'success', 'message' => __('Application rejected.')]);

        return back();
    }

    public function destroy(Request $request, PartnerApplication $application): RedirectResponse
    {
        Gate::authorize('partners.approve');

        if ($application->partner()->exists()) {
            throw ValidationException::withMessages(['application' => __('This application became a partner. Manage or suspend the partner instead.')]);
        }

        DB::transaction(function () use ($application) {
            $application->documents->each->delete();
            $application->delete();
        });

        activity('partners')->causedBy($request->user())->log('Partner application deleted: '.$application->organisation);
        $this->toast(['type' => 'success', 'message' => __('Application deleted.')]);

        return to_route('admin.partner-applications.index');
    }

    private function ensurePending(PartnerApplication $application): void
    {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('This application has already been decided.')]);
        }
    }

    /** @return array<string, mixed> */
    private static function row(PartnerApplication $a): array
    {
        [$label, $tone] = PartnerApplication::STATUSES[$a->status] ?? [$a->status, 'neutral'];

        return [
            'id' => $a->id,
            'organisation' => $a->organisation,
            'contact_name' => $a->contact_name,
            'email' => $a->email,
            'country' => $a->country?->name,
            'type' => Partner::TYPES[$a->type] ?? $a->type,
            'status' => $a->status,
            'status_label' => $label,
            'status_tone' => $tone,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
