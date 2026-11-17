<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Media;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §21.7 Testimonials and §21.8 Student Testimonials page. */
class TestimonialController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('cms.manage');

        return Inertia::render('admin/cms/Testimonials', [
            'testimonials' => Testimonial::query()->with(['photo', 'country:id,name'])->orderBy('sort_order')->orderBy('id')->get()->map(fn (Testimonial $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'role' => $t->role ?? '',
                'country_id' => $t->country_id ? (string) $t->country_id : '',
                'country' => $t->country?->name,
                'photo' => $t->photo?->present(),
                'quote' => $t->quote,
                'rating' => $t->rating,
                'is_active' => $t->is_active,
                'is_featured' => $t->is_featured,
            ]),
            'countries' => Country::query()->where('is_destination', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('cms.manage');

        Testimonial::create([...$this->validated($request), 'sort_order' => (int) Testimonial::max('sort_order') + 10]);
        $this->toast(['type' => 'success', 'message' => __('Testimonial added.')]);

        return back();
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $testimonial->update($this->validated($request));
        $this->toast(['type' => 'success', 'message' => __('Testimonial saved.')]);

        return back();
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $testimonial->delete();
        $this->toast(['type' => 'success', 'message' => __('Testimonial deleted.')]);

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $order = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']])['order'];
        DB::transaction(function () use ($order) {
            foreach (array_values($order) as $i => $id) {
                Testimonial::whereKey($id)->update(['sort_order' => ($i + 1) * 10]);
            }
        });

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:160'],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'photo_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'quote' => ['required', 'string', 'max:1500'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        return [...$data, 'is_active' => (bool) ($data['is_active'] ?? true), 'is_featured' => (bool) ($data['is_featured'] ?? false)];
    }
}
