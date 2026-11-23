<?php

namespace App\Http\Controllers\Admin\Universities;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §15 Country Management: which countries are study and skill migration
 * destinations, in what order. Used by universities and skill assessments,
 * so either module's managers can add and edit countries.
 */
class CountryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->canAny(['universities.view', 'assessments.manage']), 403);

        $search = (string) ($request->validate(['search' => ['nullable', 'string', 'max:60']])['search'] ?? '');

        return Inertia::render('admin/universities/Countries', [
            'countries' => Country::query()
                ->withCount('universities')
                ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orderByDesc('is_destination')->orderBy('sort_order')->orderBy('name')
                ->get()
                ->map(fn (Country $c) => [
                    'id' => $c->id, 'name' => $c->name, 'iso2' => $c->iso2,
                    'is_destination' => $c->is_destination, 'is_active' => $c->is_active, 'universities_count' => $c->universities_count,
                ]),
            'search' => $search,
            'canManage' => $request->user()->canAny(['universities.manage', 'assessments.manage']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAny(['universities.manage', 'assessments.manage']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique(Country::class)],
            'iso2' => ['required', 'string', 'size:2', 'alpha', Rule::unique(Country::class)],
            'is_destination' => ['boolean'],
        ], ['iso2.size' => __('Use the two-letter country code, e.g. DE for Germany.')]);

        $country = Country::create([
            'name' => $data['name'],
            'iso2' => strtoupper($data['iso2']),
            'is_destination' => (bool) ($data['is_destination'] ?? true),
            'is_active' => true,
            'sort_order' => (int) Country::query()->max('sort_order') + 10,
        ]);
        $this->toast(['type' => 'success', 'message' => __(':country added.', ['country' => $country->name])]);

        return back();
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        abort_unless($request->user()->canAny(['universities.manage', 'assessments.manage']), 403);

        if ($country->universities()->exists() || DB::table('skill_assessments')->where('country_id', $country->id)->exists()) {
            throw ValidationException::withMessages(['country' => __('Universities or a skill assessment use this country. Deactivate it instead.')]);
        }

        $country->delete();

        $this->toast(['type' => 'success', 'message' => __(':name deleted.', ['name' => $country->name])]);

        return back();
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        abort_unless($request->user()->canAny(['universities.manage', 'assessments.manage']), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80', Rule::unique(Country::class)->ignore($country)],
            'iso2' => ['sometimes', 'required', 'string', 'size:2', 'alpha', Rule::unique(Country::class)->ignore($country)],
            'is_destination' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['iso2'])) {
            $data['iso2'] = strtoupper($data['iso2']);
        }

        $country->update($data);
        $this->toast(['type' => 'success', 'message' => __(':country updated.', ['country' => $country->name])]);

        return back();
    }
}
