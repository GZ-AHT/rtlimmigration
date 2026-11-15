<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §7 "Ticket Categories". */
class TicketCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('tickets.manage');

        return Inertia::render('admin/tickets/Categories', [
            'categories' => TicketCategory::query()->withCount('tickets')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (TicketCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'is_active' => $c->is_active,
                    'tickets_count' => $c->tickets_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('tickets.manage');

        $data = $this->validated($request);
        TicketCategory::create([...$data, 'slug' => Str::slug($data['name']), 'sort_order' => (int) TicketCategory::max('sort_order') + 10]);

        $this->toast(['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    public function update(Request $request, TicketCategory $category): RedirectResponse
    {
        Gate::authorize('tickets.manage');

        $data = $this->validated($request, $category);
        $category->update([...$data, 'slug' => Str::slug($data['name'])]);

        $this->toast(['type' => 'success', 'message' => __('Category updated.')]);

        return back();
    }

    public function destroy(TicketCategory $category): RedirectResponse
    {
        Gate::authorize('tickets.manage');

        if ($category->tickets()->exists()) {
            throw ValidationException::withMessages(['category' => __('This category has tickets. Deactivate it instead.')]);
        }

        $category->delete();

        $this->toast(['type' => 'success', 'message' => __('Category deleted.')]);

        return back();
    }

    /** @return array{name: string, is_active: bool} */
    private function validated(Request $request, ?TicketCategory $category = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique(TicketCategory::class, 'slug')->ignore($category?->id)],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => __('A category with this name already exists.')]);

        return ['name' => $data['name'], 'is_active' => (bool) $data['is_active']];
    }
}
