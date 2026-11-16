<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expense records (roadmap Q12; REQ §9 "Expense Overview"). Guarded by the
 * expenses.manage permission on the routes. Admin and accounts areas.
 */
class ExpenseController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Expense::query()
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('payee', 'like', $like)->orWhere('reference', 'like', $like));
            })
            ->when($filters['category'] ?? null, fn (Builder $q, int|string $c) => $q->where('expense_category_id', $c))
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('spent_on', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('spent_on', '<=', $d));

        return Inertia::render('accounts/Expenses', [
            'expenses' => (clone $query)
                ->with(['category', 'recorder'])
                ->latest('spent_on')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Expense $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'category' => $e->category->name,
                    'category_id' => (string) $e->expense_category_id,
                    'amount_label' => Money::format($e->amount, $e->currency),
                    'amount_decimal' => Money::toDecimal($e->amount),
                    'spent_on' => $e->spent_on->toDateString(),
                    'payee' => $e->payee,
                    'reference' => $e->reference,
                    'notes' => $e->notes,
                    'recorded_by' => $e->recorder?->name,
                    'attachment_name' => $e->attachment_original_name,
                    'attachment_url' => $e->attachment_path ? $this->areaRoute($request, 'expenses.attachment', $e) : null,
                    'update_url' => $this->areaRoute($request, 'expenses.update', $e),
                    'delete_url' => $this->areaRoute($request, 'expenses.destroy', $e),
                ]),
            'filters' => ['search' => $filters['search'] ?? '', 'category' => (string) ($filters['category'] ?? ''), 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'categories' => ExpenseCategory::active()->get(['id', 'name'])->map(fn (ExpenseCategory $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'totals' => [
                'filtered' => Money::format((int) (clone $query)->sum('amount')),
                'count' => (clone $query)->count(),
                'month' => Money::format((int) Expense::whereBetween('spent_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount')),
                'year' => Money::format((int) Expense::whereYear('spent_on', now()->year)->sum('amount')),
            ],
            'currency' => config('payments.currency'),
            'attachment' => ['max_mb' => (int) floor(config('payments.expense_attachment_max_kb') / 1024), 'extensions' => config('payments.evidence_mimes')],
            'urls' => [
                'store' => $this->areaRoute($request, 'expenses.store'),
                'categories' => $this->areaRoute($request, 'expense-categories.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $stored = $request->file('attachment') instanceof UploadedFile ? $this->storeAttachment($request->file('attachment')) : [];

        Expense::create([...$data, ...$stored, 'currency' => config('payments.currency', 'BDT'), 'recorded_by' => $request->user()->id]);

        $this->toast(['type' => 'success', 'message' => __('Expense recorded.')]);

        return back();
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->file('attachment') instanceof UploadedFile) {
            $old = [$expense->attachment_disk, $expense->attachment_path];
            $data = [...$data, ...$this->storeAttachment($request->file('attachment'))];
        }

        $expense->update($data);

        if (isset($old) && $old[1] !== null) {
            Storage::disk((string) $old[0])->delete($old[1]);
        }

        $this->toast(['type' => 'success', 'message' => __('Expense updated.')]);

        return back();
    }

    /** Soft delete: the record stays in the database for audit. */
    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        $this->toast(['type' => 'success', 'message' => __('Expense removed from the books.')]);

        return back();
    }

    public function attachment(Expense $expense): StreamedResponse
    {
        abort_if($expense->attachment_path === null, 404);

        $disk = Storage::disk((string) $expense->attachment_disk);
        abort_unless($disk->exists($expense->attachment_path), 404);

        $inline = in_array($expense->attachment_mime_type, config('documents.inline'), true);

        return $disk->response($expense->attachment_path, str_replace(['"', "\r", "\n"], '', (string) $expense->attachment_original_name), [
            'Content-Type' => (string) $expense->attachment_mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique(ExpenseCategory::class, 'name')]]);

        $slug = Str::slug($data['name']) ?: Str::lower(Str::random(8));

        if (ExpenseCategory::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        ExpenseCategory::create(['name' => $data['name'], 'slug' => $slug, 'sort_order' => (int) ExpenseCategory::max('sort_order') + 10]);

        $this->toast(['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'payee' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:'.config('payments.expense_attachment_max_kb'), 'mimes:'.implode(',', config('payments.evidence_mimes'))],
        ], [
            'amount.regex' => __('Enter an amount like 5000 or 5000.50.'),
            'attachment.mimes' => __('Upload a PDF, JPG, PNG or WEBP file.'),
        ]);

        $amount = Money::toMinor($data['amount']);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }

        unset($data['attachment']);

        return [...$data, 'amount' => $amount, 'spent_on' => CarbonImmutable::parse($data['spent_on'])->toDateString()];
    }

    /** @return array{attachment_disk: string, attachment_path: string, attachment_original_name: string, attachment_mime_type: string} */
    private function storeAttachment(UploadedFile $file): array
    {
        $disk = (string) config('documents.disk');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs('expenses/'.now()->format('Y'), Str::uuid()->toString().'.'.$extension, $disk);

        abort_if($path === false, 500, __('The file could not be saved. Please try again.'));

        return [
            'attachment_disk' => $disk,
            'attachment_path' => $path,
            'attachment_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'attachment_mime_type' => (string) $file->getMimeType(),
        ];
    }
}
