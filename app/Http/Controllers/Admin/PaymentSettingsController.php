<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethodKind;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentType;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §12.2 "Admin should be able to create payment types dynamically" and
 * §12.3 / §9.1 "Payment Methods". Types and methods in use are deactivated,
 * never deleted, so history keeps its meaning.
 */
class PaymentSettingsController extends Controller
{
    public function types(): Response
    {
        Gate::authorize('payment-types.manage');

        return Inertia::render('admin/payments/Types', [
            'types' => PaymentType::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (PaymentType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'default_amount' => $t->default_amount !== null ? Money::toDecimal($t->default_amount) : null,
                'default_amount_label' => $t->default_amount !== null ? Money::format($t->default_amount) : null,
                'generate_on_file_open' => $t->generate_on_file_open,
                'is_active' => $t->is_active,
                'in_use' => PaymentRequest::where('payment_type_id', $t->id)->exists(),
            ]),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        Gate::authorize('payment-types.manage');

        PaymentType::create([...$this->typeData($request), 'sort_order' => (int) PaymentType::max('sort_order') + 10]);
        $this->toast(['type' => 'success', 'message' => __('Payment type added.')]);

        return back();
    }

    public function updateType(Request $request, PaymentType $type): RedirectResponse
    {
        Gate::authorize('payment-types.manage');

        $type->update($this->typeData($request, $type));
        $this->toast(['type' => 'success', 'message' => __('Payment type updated.')]);

        return back();
    }

    public function destroyType(PaymentType $type): RedirectResponse
    {
        Gate::authorize('payment-types.manage');

        if (PaymentRequest::where('payment_type_id', $type->id)->exists()) {
            throw ValidationException::withMessages(['type' => __('Invoices use this type. Deactivate it instead.')]);
        }

        $type->delete();
        $this->toast(['type' => 'success', 'message' => __('Payment type deleted.')]);

        return back();
    }

    public function methods(): Response
    {
        Gate::authorize('payment-types.manage');

        return Inertia::render('admin/payments/Methods', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (PaymentMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'kind' => $m->kind->value,
                'kind_label' => $m->kind->label(),
                'instructions' => $m->instructions,
                'requires_reference' => $m->requires_reference,
                'requires_evidence' => $m->requires_evidence,
                'student_selectable' => $m->student_selectable,
                'is_active' => $m->is_active,
                'in_use' => Payment::where('payment_method_id', $m->id)->exists(),
            ]),
            'kinds' => array_values(array_filter(PaymentMethodKind::options(), fn ($o) => $o['value'] !== 'gateway')),
        ]);
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        Gate::authorize('payment-types.manage');

        PaymentMethod::create([...$this->methodData($request), 'sort_order' => (int) PaymentMethod::max('sort_order') + 10]);
        $this->toast(['type' => 'success', 'message' => __('Payment method added.')]);

        return back();
    }

    public function updateMethod(Request $request, PaymentMethod $method): RedirectResponse
    {
        Gate::authorize('payment-types.manage');

        $method->update($this->methodData($request, $method));
        $this->toast(['type' => 'success', 'message' => __('Payment method updated.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function typeData(Request $request, ?PaymentType $type = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique(PaymentType::class, 'slug')->ignore($type?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'default_amount' => ['nullable', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'generate_on_file_open' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => __('A payment type with this name already exists.'), 'default_amount.regex' => __('Enter an amount like 5000 or 5000.50.')]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'default_amount' => filled($data['default_amount'] ?? null) ? Money::toMinor((string) $data['default_amount']) : null,
            'generate_on_file_open' => (bool) $data['generate_on_file_open'],
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** @return array<string, mixed> */
    private function methodData(Request $request, ?PaymentMethod $method = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique(PaymentMethod::class, 'slug')->ignore($method?->id)],
            // Gateways are configured with the Phase 5 integration, not by hand.
            'kind' => ['required', Rule::enum(PaymentMethodKind::class)->except([PaymentMethodKind::Gateway])],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'requires_reference' => ['required', 'boolean'],
            'requires_evidence' => ['required', 'boolean'],
            'student_selectable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => __('A payment method with this name already exists.')]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'kind' => $data['kind'],
            'instructions' => $data['instructions'] ?? null,
            'requires_reference' => (bool) $data['requires_reference'],
            'requires_evidence' => (bool) $data['requires_evidence'],
            'student_selectable' => (bool) $data['student_selectable'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
