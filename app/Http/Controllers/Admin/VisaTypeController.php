<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VisaKind;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DocumentCategory;
use App\Models\PaymentType;
use App\Models\VisaRequirement;
use App\Models\VisaType;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §17 "Visa Categories", "Visa Requirements", "Required Documents",
 * "Visa Fees" (roadmap Q14: each visa type carries its own). Guarded by
 * workflows.manage on the routes.
 */
class VisaTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/visa/Types', [
            'types' => VisaType::query()->with(['requirements.country', 'documentCategories', 'paymentType'])->withCount('applications')->orderBy('sort_order')->orderBy('name')->get()->map(fn (VisaType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'kind' => $t->kind->value,
                'kind_label' => $t->kind->label(),
                'description' => $t->description,
                'is_active' => $t->is_active,
                'payment_type_id' => $t->payment_type_id ? (string) $t->payment_type_id : '',
                'fee_decimal' => $t->fee_amount ? Money::toDecimal($t->fee_amount) : '',
                'fee_label' => $t->fee_amount && $t->paymentType ? Money::format($t->fee_amount).' · '.$t->paymentType->name : null,
                'applications_count' => $t->getAttribute('applications_count'),
                'document_category_ids' => $t->documentCategories->map(fn (DocumentCategory $c) => (string) $c->id)->values(),
                'documents' => $t->documentCategories->pluck('name')->values(),
                'requirements' => $t->requirements->map(fn (VisaRequirement $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'country' => $r->country?->name,
                    'delete_url' => route('admin.visa-types.requirements.destroy', [$t, $r], false),
                ])->values(),
                'update_url' => route('admin.visa-types.update', $t, false),
                'requirement_url' => route('admin.visa-types.requirements.store', $t, false),
            ]),
            'kinds' => VisaKind::options(),
            'paymentTypes' => PaymentType::active()->get(['id', 'name'])->map(fn (PaymentType $p) => ['value' => (string) $p->id, 'label' => $p->name])->values(),
            'documentCategories' => DocumentCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])->map(fn (DocumentCategory $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'currency' => config('payments.currency'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = Str::slug($data['name']) ?: Str::lower(Str::random(8));

        if (VisaType::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $type = VisaType::create([...$data['attributes'], 'slug' => $slug, 'sort_order' => (int) VisaType::max('sort_order') + 10]);
        $type->documentCategories()->sync($data['documents']);

        $this->toast(['type' => 'success', 'message' => __('Visa type added. It uses the default workflow until you give it its own.')]);

        return back();
    }

    public function update(Request $request, VisaType $visaType): RedirectResponse
    {
        $data = $this->validated($request, $visaType);

        $visaType->update($data['attributes']);
        $visaType->documentCategories()->sync($data['documents']);

        $this->toast(['type' => 'success', 'message' => __('Visa type saved.')]);

        return back();
    }

    public function storeRequirement(Request $request, VisaType $visaType): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
        ]);

        $visaType->requirements()->create([...$data, 'sort_order' => (int) $visaType->requirements()->max('sort_order') + 10]);

        $this->toast(['type' => 'success', 'message' => __('Requirement added.')]);

        return back();
    }

    public function destroyRequirement(VisaType $visaType, VisaRequirement $requirement): RedirectResponse
    {
        abort_unless($requirement->visa_type_id === $visaType->id, 404);
        $requirement->delete();

        $this->toast(['type' => 'success', 'message' => __('Requirement removed.')]);

        return back();
    }

    /** @return array{name: string, attributes: array<string, mixed>, documents: list<int>} */
    private function validated(Request $request, ?VisaType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique(VisaType::class, 'name')->ignore($type?->id)],
            'kind' => ['required', Rule::enum(VisaKind::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'payment_type_id' => ['nullable', 'integer', Rule::exists(PaymentType::class, 'id'), 'required_with:fee'],
            'fee' => ['nullable', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'document_category_ids' => ['array'],
            'document_category_ids.*' => ['integer', Rule::exists(DocumentCategory::class, 'id')],
        ], [
            'fee.regex' => __('Enter an amount like 5000 or 5000.50.'),
            'payment_type_id.required_with' => __('Choose which fee type the amount is invoiced as.'),
        ]);

        $fee = filled($data['fee'] ?? null) ? Money::toMinor($data['fee']) : null;

        return [
            'name' => $data['name'],
            'attributes' => [
                'name' => $data['name'],
                'kind' => $data['kind'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'fee_amount' => $fee ?: null,
            ],
            'documents' => array_values(array_map(intval(...), $data['document_category_ids'] ?? [])),
        ];
    }
}
