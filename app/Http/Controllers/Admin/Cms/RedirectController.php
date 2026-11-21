<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Support\Cms\SectionSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §22.3: 301 / 302 redirect management. */
class RedirectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('seo.manage');

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/seo/Redirects', [
            'redirects' => Redirect::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('from_path', 'like', '%'.$search.'%')->orWhere('to_url', 'like', '%'.$search.'%')))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (Redirect $r) => [
                    'id' => $r->id, 'from_path' => $r->from_path, 'to_url' => $r->to_url, 'status_code' => $r->status_code,
                    'is_active' => $r->is_active, 'hits' => $r->hits, 'last_hit_at' => $r->last_hit_at?->diffForHumans(), 'note' => $r->note ?? '',
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('seo.manage');

        $redirect = Redirect::create([...$this->validated($request), 'created_by' => $request->user()->id]);
        activity('cms')->causedBy($request->user())->performedOn($redirect)->log('Redirect added');
        $this->toast(['type' => 'success', 'message' => __('Redirect added.')]);

        return back();
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        Gate::authorize('seo.manage');

        $redirect->update($this->validated($request, $redirect));
        activity('cms')->causedBy($request->user())->performedOn($redirect)->log('Redirect updated');
        $this->toast(['type' => 'success', 'message' => __('Redirect saved.')]);

        return back();
    }

    public function destroy(Request $request, Redirect $redirect): RedirectResponse
    {
        Gate::authorize('seo.manage');

        $redirect->delete();
        activity('cms')->causedBy($request->user())->log('Redirect deleted');
        $this->toast(['type' => 'success', 'message' => __('Redirect deleted.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Redirect $current = null): array
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'regex:~^/?[^\s<>"\']+$~'],
            'to_url' => ['required', 'string', 'max:500', fn ($a, $v, $fail) => (SectionSanitizer::safeUrl(trim((string) $v)) && preg_match('~^(/(?!/)|https?://)~i', trim((string) $v))) || $fail(__('Use a site path like /new-page, or a full https:// address.'))],
            'status_code' => ['required', 'in:301,302'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $from = Redirect::normalize($data['from_path']);

        if ($from === '/' || preg_match('~^/(admin|dashboard|settings|email|payments|chat|build|up|login|logout)(/|$)~', $from)) {
            throw ValidationException::withMessages(['from_path' => __('That path can’t be redirected.')]);
        }

        if (Redirect::query()->where('from_path', $from)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists()) {
            throw ValidationException::withMessages(['from_path' => __('A redirect from this path already exists.')]);
        }

        // Follow the chain from the target: it must not lead back to this path.
        $to = trim($data['to_url']);
        $seen = [$from];
        $hop = str_starts_with($to, '/') ? Redirect::normalize($to) : null;
        $map = Redirect::query()->where('is_active', true)->pluck('to_url', 'from_path');

        while ($hop !== null && count($seen) < 12) {
            if (in_array($hop, $seen, true)) {
                throw ValidationException::withMessages(['to_url' => __('This would send visitors around in a loop.')]);
            }
            $seen[] = $hop;
            $next = $map[$hop] ?? null;
            $hop = is_string($next) && str_starts_with($next, '/') ? Redirect::normalize($next) : null;
        }

        return ['from_path' => $from, 'to_url' => $to, 'status_code' => (int) $data['status_code'], 'is_active' => (bool) ($data['is_active'] ?? true), 'note' => $data['note'] ?? null];
    }
}
