<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** REQ §21.4 / §21.7 newsletter subscribers: list, export, remove. */
class NewsletterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('cms.manage');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['subscribed', 'unsubscribed'])],
        ]);

        return Inertia::render('admin/cms/Newsletter', [
            'subscribers' => $this->query($filters)->latest('id')->paginate(30)->withQueryString()->through(fn (NewsletterSubscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'status' => $s->status,
                'source' => $s->source,
                'created_at' => $s->created_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ]),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
            'counts' => NewsletterSubscriber::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('cms.manage');

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['subscribed', 'unsubscribed'])]]);
        activity('cms')->causedBy($request->user())->log('Newsletter subscribers exported');

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['email', 'status', 'source', 'subscribed_at']);
            $this->query($filters)->orderBy('id')->each(function (NewsletterSubscriber $s) use ($out) {
                // Guard against spreadsheet formula injection.
                $email = preg_match('/^[=+\-@]/', $s->email) ? "'".$s->email : $s->email;
                fputcsv($out, [$email, $s->status, $s->source, $s->created_at?->toDateTimeString()]);
            });
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $subscriber->delete();
        $this->toast(['type' => 'success', 'message' => __('Subscriber removed.')]);

        return back();
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return Builder<NewsletterSubscriber>
     */
    private function query(array $filters): Builder
    {
        return NewsletterSubscriber::query()
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where('email', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s));
    }
}
