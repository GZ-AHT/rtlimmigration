<?php

namespace App\Http\Controllers\Admin\Universities;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §15 Country Management: which countries are study destinations and in what order. */
class CountryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('universities.view');

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
            'canManage' => $request->user()->can('universities.manage'),
        ]);
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        Gate::authorize('universities.manage');

        $data = $request->validate(['is_destination' => ['boolean'], 'is_active' => ['boolean']]);
        $country->update($data);
        $this->toast(['type' => 'success', 'message' => __(':country updated.', ['country' => $country->name])]);

        return back();
    }
}
