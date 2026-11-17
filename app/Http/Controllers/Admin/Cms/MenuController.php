<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\Cms\SectionSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * REQ §21.3 Dynamic Menu Management: menus per location, items with
 * internal page / custom / external links, new tab, parent and child
 * (one level of dropdown), drag-and-drop order and visibility by role.
 */
class MenuController extends Controller
{
    public const CACHE_KEY = 'website.menus';

    public function index(Request $request): Response
    {
        Gate::authorize('cms.manage');

        foreach (Menu::LOCATIONS as $location => $name) {
            Menu::firstOrCreate(['location' => $location], ['name' => $name]);
        }

        $location = (string) ($request->validate(['location' => ['nullable', Rule::in(array_keys(Menu::LOCATIONS))]])['location'] ?? 'main');
        $menu = Menu::where('location', $location)->firstOrFail();
        $items = $menu->items()->with('page:id,title,slug,system_key')->get();

        return Inertia::render('admin/cms/Menus', [
            'locations' => collect(Menu::LOCATIONS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'location' => $location,
            'menu' => ['id' => $menu->id, 'name' => $menu->name],
            'items' => $items->whereNull('parent_id')->values()->map(fn (MenuItem $i) => [
                ...self::item($i),
                'children' => $items->where('parent_id', $i->id)->values()->map(fn (MenuItem $c) => [...self::item($c), 'children' => []])->all(),
            ]),
            'pages' => Page::query()->orderByRaw('system_key is null')->orderBy('title')->get(['id', 'title', 'slug', 'system_key'])
                ->map(fn (Page $p) => ['value' => (string) $p->id, 'label' => $p->title.' ('.($p->system_key === 'home' ? '/' : '/'.$p->slug).')'])->values(),
            'roles' => RoleModel::query()->orderBy('name')->pluck('name')->map(fn ($r) => ['value' => $r, 'label' => Role::tryFrom($r)?->label() ?? $r])->values(),
        ]);
    }

    public function store(Request $request, Menu $menu): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $data = $this->validated($request, $menu);
        $menu->items()->create([...$data, 'sort_order' => (int) $menu->items()->where('parent_id', $data['parent_id'])->max('sort_order') + 10]);
        $this->flush();
        $this->toast(['type' => 'success', 'message' => __('Menu link added.')]);

        return back();
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $data = $this->validated($request, $item->menu, $item);
        $item->update($data);
        $this->flush();
        $this->toast(['type' => 'success', 'message' => __('Menu link saved.')]);

        return back();
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $item->delete();
        $this->flush();
        $this->toast(['type' => 'success', 'message' => __('Menu link deleted.')]);

        return back();
    }

    /** Drag-and-drop result: [{id, children: [{id}]}] in display order. */
    public function reorder(Request $request, Menu $menu): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $tree = $request->validate([
            'tree' => ['required', 'array'],
            'tree.*.id' => ['required', 'integer'],
            'tree.*.children' => ['array'],
            'tree.*.children.*.id' => ['required', 'integer'],
        ])['tree'];

        $ids = $menu->items()->pluck('id')->all();

        DB::transaction(function () use ($tree, $ids) {
            foreach (array_values($tree) as $i => $node) {
                abort_unless(in_array($node['id'], $ids, true), 422);
                MenuItem::whereKey($node['id'])->update(['parent_id' => null, 'sort_order' => ($i + 1) * 10]);

                foreach (array_values($node['children'] ?? []) as $j => $child) {
                    abort_unless(in_array($child['id'], $ids, true) && $child['id'] !== $node['id'], 422);
                    MenuItem::whereKey($child['id'])->update(['parent_id' => $node['id'], 'sort_order' => ($j + 1) * 10]);
                }
            }
        });

        $this->flush();

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Menu $menu, ?MenuItem $item = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'link_type' => ['required', Rule::in(['page', 'url'])],
            'page_id' => ['nullable', 'required_if:link_type,page', 'integer', Rule::exists(Page::class, 'id')],
            'url' => ['nullable', 'required_if:link_type,url', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists(MenuItem::class, 'id')->where('menu_id', $menu->id)->whereNull('parent_id')],
            'new_tab' => ['boolean'],
            'is_active' => ['boolean'],
            'visibility' => ['required', Rule::in(['everyone', 'guests', 'auth', 'roles'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ], ['url.required_if' => __('Enter the address, e.g. /contact or https://…')]);

        if ($data['link_type'] === 'url' && ! SectionSanitizer::safeUrl(trim((string) $data['url']))) {
            throw ValidationException::withMessages(['url' => __('Use a site path like /contact, or a full https:// address.')]);
        }

        if ($item !== null && isset($data['parent_id']) && ((int) $data['parent_id'] === $item->id || MenuItem::where('parent_id', $item->id)->exists())) {
            throw ValidationException::withMessages(['parent_id' => __('A link with its own dropdown can\'t go inside another dropdown.')]);
        }

        return [
            'label' => $data['label'],
            'link_type' => $data['link_type'],
            'page_id' => $data['link_type'] === 'page' ? $data['page_id'] : null,
            'url' => $data['link_type'] === 'url' ? trim((string) $data['url']) : null,
            'parent_id' => $data['parent_id'] ?? null,
            'new_tab' => (bool) ($data['new_tab'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'visibility' => $data['visibility'],
            'roles' => $data['visibility'] === 'roles' ? array_values($data['roles'] ?? []) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function item(MenuItem $i): array
    {
        return [
            'id' => $i->id,
            'label' => $i->label,
            'link_type' => $i->link_type,
            'page_id' => $i->page_id ? (string) $i->page_id : '',
            'url' => $i->url ?? '',
            'href' => $i->href(),
            'parent_id' => $i->parent_id,
            'new_tab' => $i->new_tab,
            'is_active' => $i->is_active,
            'visibility' => $i->visibility,
            'roles' => $i->roles ?? [],
        ];
    }

    private function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
