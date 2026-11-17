<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Support\Email\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §3 Email Templates and Categories / Labels; §19.1 "Email Templates managed by Laravel". */
class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('email.manage');

        return Inertia::render('admin/email/Templates', [
            'templates' => EmailTemplate::query()->orderBy('category')->orderBy('name')->get()->map(fn (EmailTemplate $t) => self::present($t)),
            'placeholders' => TemplateRenderer::PLACEHOLDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('email.manage');

        EmailTemplate::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        $this->toast(['type' => 'success', 'message' => __('Template added.')]);

        return back();
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        Gate::authorize('email.manage');

        $template->update($this->validated($request, $template));

        $this->toast(['type' => 'success', 'message' => __('Template saved.')]);

        return back();
    }

    public function destroy(EmailTemplate $template): RedirectResponse
    {
        Gate::authorize('email.manage');

        // Sent mail keeps its own copy of the text, so deleting a template loses no history.
        $template->delete();

        $this->toast(['type' => 'success', 'message' => __('Template deleted.')]);

        return back();
    }

    /** @return array{id: int, name: string, category: string|null, subject: string, body: string, is_active: bool} */
    public static function present(EmailTemplate $t): array
    {
        return ['id' => $t->id, 'name' => $t->name, 'category' => $t->category, 'subject' => $t->subject, 'body' => $t->body, 'is_active' => $t->is_active];
    }

    /** @return array{name: string, slug: string, category: string|null, subject: string, body: string, is_active: bool} */
    private function validated(Request $request, ?EmailTemplate $template = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'max:140', Rule::unique(EmailTemplate::class, 'slug')->ignore($template?->id)],
            'category' => ['nullable', 'string', 'max:60'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => __('A template with this name already exists.')]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
