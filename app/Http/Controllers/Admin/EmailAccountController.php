<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailAccountStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\User;
use App\Support\Email\CpanelException;
use App\Support\Email\EmailSender;
use App\Support\Email\EmailSettings;
use App\Support\Email\MailboxManager;
use App\Support\Presenters\EmailPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/** REQ §19.2 Email Account Management, Create Email Account, Assign Email to User / Counselor, Mailbox Limits. */
class EmailAccountController extends Controller
{
    public function index(Request $request, EmailSettings $settings): Response
    {
        Gate::authorize('email.manage');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(EmailAccountStatus::class)],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        $accounts = EmailAccount::query()
            ->with('users:id,name')
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('email', 'like', $like)->orWhere('display_name', 'like', $like)
                    ->orWhereHas('users', fn (Builder $u) => $u->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['domain'] ?? null, fn (Builder $q, string $d) => $q->where('domain', $d))
            ->orderBy('email')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EmailAccount $a) => EmailPresenter::account($a));

        $domains = collect($settings->domains())->merge(EmailAccount::query()->distinct()->pluck('domain'))->unique()->sort()->values();

        return Inertia::render('admin/email/Accounts', [
            'accounts' => $accounts,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'domain' => $filters['domain'] ?? ''],
            'options' => [
                'statuses' => EmailAccountStatus::options(),
                'domains' => $domains->map(fn ($d) => ['value' => $d, 'label' => $d])->all(),
                'staff' => $this->staffOptions(),
            ],
            'connected' => $settings->cpanelConfigured(),
            'defaultQuotaMb' => $settings->defaultQuotaMb(),
            'activity' => Activity::query()->where('log_name', 'email')->with('causer:id,name')->latest('id')->limit(15)->get()
                ->map(fn (Activity $a) => EmailPresenter::activity($a)),
        ]);
    }

    public function store(Request $request, MailboxManager $manager, EmailSettings $settings): RedirectResponse
    {
        Gate::authorize('email.manage');

        $request->merge([
            'local_part' => strtolower(trim((string) $request->input('local_part'))),
            'domain' => strtolower(trim((string) $request->input('domain'))),
        ]);
        $request->merge(['email' => $request->input('local_part').'@'.$request->input('domain')]);

        $data = $request->validate([
            // cPanel's own rule for the part before @.
            'local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/'],
            'domain' => ['required', 'string', 'max:255', $settings->domains() !== [] ? Rule::in($settings->domains()) : 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/'],
            'email' => ['email:rfc', Rule::unique(EmailAccount::class, 'email')],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:128', Password::min(12)->mixedCase()->numbers()],
            'quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'users' => ['array'],
            'users.*' => ['integer', Rule::exists('users', 'id')],
        ], [
            'local_part.regex' => __('Use letters, numbers, dots, dashes, plus or underscores, starting and ending with a letter or number.'),
            'domain.in' => __('Choose one of the mail domains set in Email settings.'),
            'email.unique' => __('This address already exists.'),
        ]);

        $account = $this->guard(fn () => $manager->create($data, $request->user()));

        if (! empty($data['users'])) {
            $manager->assign($account, array_fill_keys(array_map('intval', $data['users']), true), $request->user());
        }

        $this->toast(['type' => 'success', 'message' => $account->on_server
            ? __(':email created on the mail server.', ['email' => $account->email])
            : __(':email recorded. It will be created on the server once cPanel is connected.', ['email' => $account->email])]);

        return back();
    }

    public function update(Request $request, EmailAccount $account, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'users' => ['present', 'array'],
            'users.*.id' => ['required', 'integer', Rule::exists('users', 'id')],
            'users.*.can_send' => ['required', 'boolean'],
        ]);

        $this->guard(fn () => $manager->setQuota($account, isset($data['quota_mb']) ? (int) $data['quota_mb'] : null, $request->user()));
        $account->update(['display_name' => $data['display_name'] ?? null]);
        $assignments = [];
        foreach ($data['users'] as $row) {
            $assignments[(int) $row['id']] = (bool) $row['can_send'];
        }
        $manager->assign($account, $assignments, $request->user());

        $this->toast(['type' => 'success', 'message' => __('Mailbox updated.')]);

        return back();
    }

    public function password(Request $request, EmailAccount $account, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:128', Password::min(12)->mixedCase()->numbers()],
            'generate' => ['boolean'],
        ]);

        if (blank($data['password'] ?? null) && ! ($data['generate'] ?? false)) {
            throw ValidationException::withMessages(['password' => __('Enter a password or choose to generate one.')]);
        }

        if (! $account->on_server && blank($data['password'] ?? null)) {
            throw ValidationException::withMessages(['password' => __('This mailbox is not managed through cPanel here. Enter its real password so mail can be sent from it.')]);
        }

        $password = filled($data['password'] ?? null) ? (string) $data['password'] : MailboxManager::generatePassword();
        $this->guard(fn () => $manager->changePassword($account, $password, $request->user()));

        $this->toast(['type' => 'success', 'message' => __('Password updated. Staff keep signing in to webmail from the panel; they never need it.')]);

        return back();
    }

    public function status(Request $request, EmailAccount $account, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate(['status' => ['required', Rule::enum(EmailAccountStatus::class)]]);
        $status = EmailAccountStatus::from($data['status']);

        $this->guard(fn () => $manager->setStatus($account, $status, $request->user()));

        $this->toast(['type' => 'success', 'message' => $status === EmailAccountStatus::Suspended ? __(':email suspended.', ['email' => $account->email]) : __(':email resumed.', ['email' => $account->email])]);

        return back();
    }

    public function provision(Request $request, EmailAccount $account, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        if ($account->on_server) {
            throw ValidationException::withMessages(['account' => __('This mailbox is already on the mail server.')]);
        }

        $this->guard(fn () => $manager->provision($account, $request->user()));

        $this->toast(['type' => 'success', 'message' => __(':email created on the mail server.', ['email' => $account->email])]);

        return back();
    }

    public function destroy(Request $request, EmailAccount $account, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate(['from_server' => ['required', 'boolean']]);
        $this->guard(fn () => $manager->delete($account, (bool) $data['from_server'], $request->user()));

        $this->toast(['type' => 'success', 'message' => __('Mailbox removed.')]);

        return back();
    }

    public function sync(Request $request, MailboxManager $manager): RedirectResponse
    {
        Gate::authorize('email.manage');

        $result = $this->guard(fn () => $manager->sync($request->user()));

        $this->toast(['type' => 'success', 'message' => __('Synced: :imported imported, :updated updated, :missing no longer on the server.', $result)]);

        return back();
    }

    public function test(Request $request, EmailAccount $account, EmailSender $sender): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:255']]);

        try {
            $sender->sendTest($account, $data['to']);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['to' => __('Sending failed: :error', ['error' => str($e->getMessage())->limit(200)])]);
        }

        activity('email')->causedBy($request->user())->performedOn($account)->withProperties(['email' => $account->email])->log('Test email sent');
        $this->toast(['type' => 'success', 'message' => __('Test email sent to :to.', ['to' => $data['to']])]);

        return back();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function guard(callable $action): mixed
    {
        try {
            return $action();
        } catch (CpanelException $e) {
            throw ValidationException::withMessages(['cpanel' => $e->getMessage()]);
        }
    }

    /** @return list<array{value: string, label: string}> */
    private function staffOptions(): array
    {
        return array_values(User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', '!=', Role::Student->value))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->all());
    }
}
