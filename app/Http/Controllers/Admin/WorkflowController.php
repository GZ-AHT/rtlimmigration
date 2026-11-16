<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StageKind;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Country;
use App\Models\VisaType;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §13: "Stages should be configurable by Super Admin because different
 * countries and visa types may have different workflows." Guarded by
 * workflows.manage on the routes.
 */
class WorkflowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/visa/Workflows', [
            'workflows' => Workflow::query()->with(['stages', 'visaType', 'country'])->withCount('applications')
                ->orderByRaw('visa_type_id IS NOT NULL')->orderByRaw('country_id IS NOT NULL')->orderBy('name')->get()
                ->map(fn (Workflow $w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'is_default' => $w->isDefault(),
                    'is_active' => $w->is_active,
                    'visa_type_id' => $w->visa_type_id ? (string) $w->visa_type_id : '',
                    'country_id' => $w->country_id ? (string) $w->country_id : '',
                    'applies_to' => $w->isDefault()
                        ? 'Every application without a more specific workflow'
                        : implode(' · ', [$w->visaType ? $w->visaType->name : 'Any visa type', $w->country ? $w->country->name : 'Any country']),
                    'applications_count' => $w->getAttribute('applications_count'),
                    'stages' => $w->stages->map(fn (WorkflowStage $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'kind' => $s->kind->value,
                        'kind_label' => $s->kind->label(),
                        'description' => $s->description,
                        'notify_student' => $s->notify_student,
                        'update_url' => route('admin.workflows.stages.update', [$w, $s], false),
                        'delete_url' => route('admin.workflows.stages.destroy', [$w, $s], false),
                    ])->values(),
                    'update_url' => route('admin.workflows.update', $w, false),
                    'delete_url' => route('admin.workflows.destroy', $w, false),
                    'stage_url' => route('admin.workflows.stages.store', $w, false),
                    'reorder_url' => route('admin.workflows.stages.reorder', $w, false),
                ]),
            'kinds' => StageKind::options(),
            'types' => VisaType::query()->orderBy('sort_order')->get(['id', 'name'])->map(fn (VisaType $t) => ['value' => (string) $t->id, 'label' => $t->name])->values(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'urls' => ['store' => route('admin.workflows.store', absolute: false)],
        ]);
    }

    /** A new workflow starts as a copy of another's stages, so it is usable at once. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $source = Workflow::with('stages')->find($request->integer('copy_from')) ?? Workflow::with('stages')->whereNull('visa_type_id')->whereNull('country_id')->first();

        DB::transaction(function () use ($data, $source) {
            $workflow = Workflow::create($data);

            foreach ($source->stages ?? [] as $stage) {
                $workflow->stages()->create($stage->only(['name', 'kind', 'description', 'notify_student', 'sort_order']));
            }
        });

        $this->toast(['type' => 'success', 'message' => __('Workflow created with a copy of the stages. Edit them to suit.')]);

        return back();
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $this->validated($request, $workflow);

        // The default must stay the catch-all, and stay on.
        if ($workflow->isDefault()) {
            $data = ['name' => $data['name']];
        }

        $workflow->update($data);

        $this->toast(['type' => 'success', 'message' => __('Workflow saved.')]);

        return back();
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        if ($workflow->isDefault()) {
            throw ValidationException::withMessages(['workflow' => __('The default workflow cannot be deleted.')]);
        }

        if ($workflow->applications()->exists()) {
            throw ValidationException::withMessages(['workflow' => __('Applications are using this workflow. Switch it off instead of deleting it.')]);
        }

        $workflow->delete();

        $this->toast(['type' => 'success', 'message' => __('Workflow deleted.')]);

        return back();
    }

    public function storeStage(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $this->validatedStage($request, $workflow);

        $workflow->stages()->create([...$data, 'sort_order' => (int) $workflow->stages()->max('sort_order') + 10]);

        $this->toast(['type' => 'success', 'message' => __('Stage added at the end. Drag-free reordering: use the arrows.')]);

        return back();
    }

    public function updateStage(Request $request, Workflow $workflow, WorkflowStage $stage): RedirectResponse
    {
        abort_unless($stage->workflow_id === $workflow->id, 404);

        $stage->update($this->validatedStage($request, $workflow, $stage));

        $this->toast(['type' => 'success', 'message' => __('Stage saved.')]);

        return back();
    }

    /** Retire a stage. History keeps its name; no application may be sitting on it. */
    public function destroyStage(Workflow $workflow, WorkflowStage $stage): RedirectResponse
    {
        abort_unless($stage->workflow_id === $workflow->id, 404);

        if (Application::where('current_stage_id', $stage->id)->exists()) {
            throw ValidationException::withMessages(['stage' => __('Applications are currently at this stage. Move them on first.')]);
        }

        if ($workflow->stages()->count() <= 1) {
            throw ValidationException::withMessages(['stage' => __('A workflow needs at least one stage.')]);
        }

        $stage->delete();

        $this->toast(['type' => 'success', 'message' => __('Stage removed.')]);

        return back();
    }

    public function reorder(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $ids = $workflow->stages()->pluck('id')->all();

        if (array_diff($ids, $data['order']) !== [] || count($ids) !== count($data['order'])) {
            throw ValidationException::withMessages(['order' => __('The stage list changed. Reload and try again.')]);
        }

        DB::transaction(function () use ($data) {
            foreach (array_values($data['order']) as $position => $id) {
                WorkflowStage::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        return back();
    }

    /** @return array{name: string, visa_type_id: int|null, country_id: int|null, is_active: bool} */
    private function validated(Request $request, ?Workflow $workflow = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'visa_type_id' => ['nullable', 'integer', Rule::exists(VisaType::class, 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'is_active' => ['boolean'],
        ]);

        $typeId = isset($data['visa_type_id']) ? (int) $data['visa_type_id'] : null;
        $countryId = isset($data['country_id']) ? (int) $data['country_id'] : null;

        if (! $workflow?->isDefault()) {
            if ($typeId === null && $countryId === null) {
                throw ValidationException::withMessages(['visa_type_id' => __('Choose a visa type, a country, or both. Only the default workflow covers everything.')]);
            }

            // Two workflows for the same combination would make the choice arbitrary.
            $clash = Workflow::query()->where('visa_type_id', $typeId)->where('country_id', $countryId)->when($workflow, fn ($q) => $q->whereKeyNot($workflow->id))->exists();

            if ($clash) {
                throw ValidationException::withMessages(['visa_type_id' => __('A workflow for this visa type and country already exists.')]);
            }
        }

        return ['name' => $data['name'], 'visa_type_id' => $typeId, 'country_id' => $countryId, 'is_active' => $data['is_active'] ?? true];
    }

    /** @return array{name: string, kind: string, description: string|null, notify_student: bool} */
    private function validatedStage(Request $request, Workflow $workflow, ?WorkflowStage $stage = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(StageKind::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'notify_student' => ['boolean'],
        ]);

        // One decision and one final stage per workflow keeps the rules unambiguous.
        if (in_array($data['kind'], [StageKind::Decision->value, StageKind::Final->value], true)
            && $workflow->stages()->where('kind', $data['kind'])->when($stage, fn ($q) => $q->whereKeyNot($stage->id))->exists()) {
            throw ValidationException::withMessages(['kind' => __('This workflow already has a stage of that kind.')]);
        }

        return ['name' => $data['name'], 'kind' => $data['kind'], 'description' => $data['description'] ?? null, 'notify_student' => $data['notify_student'] ?? true];
    }
}
