<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserStatus;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\SaveUser;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Policies\UserPolicy;
use App\Support\Presenters\ActivityPresenter;
use App\Support\Presenters\UserPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** REQ §6 User Management. */
class UserController extends Controller
{
    private const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in([...array_column(UserStatus::cases(), 'value'), 'deleted', 'unverified'])],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => match ($status) {
                'deleted' => $q->onlyTrashed(),
                'unverified' => $q->whereNull('email_verified_at'),
                default => $q->where('status', $status),
            })
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => UserPresenter::row($u, $request->user()));

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
            'roles' => UserPresenter::roleOptions($request->user()),
            'statuses' => UserPresenter::statusOptions(),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/Form', [
            'user' => null,
            'roles' => UserPresenter::roleOptions($request->user()),
            'statuses' => UserPresenter::statusOptions(),
            'canChangeAccess' => true,
        ]);
    }

    public function store(UserRequest $request, SaveUser $save): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $user = $save->create($request->payload() + ['status' => UserStatus::Active->value, 'roles' => []], $request->user());

        $this->toast([
            'type' => 'success',
            'message' => ($request->boolean('send_invitation', true))
                ? __('User created. An invitation email has been sent.')
                : __('User created.'),
        ]);

        return to_route('admin.users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load('roles:id,name');

        return Inertia::render('admin/users/Show', [
            'user' => UserPresenter::detail($user, $request->user()),
            'logins' => $user->loginHistories()->latest('created_at')->limit(15)->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'event' => $l->event,
                    'ip_address' => $l->ip_address,
                    'user_agent' => $l->user_agent,
                    'created_at' => $l->created_at->toIso8601String(),
                ]),
            'activity' => Activity::query()
                ->where(fn ($q) => $q->whereMorphedTo('subject', $user)->orWhereMorphedTo('causer', $user))
                ->with(['causer:id,name', 'subject'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Activity $a) => ActivityPresenter::present($a)),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        $user->load('roles:id,name');

        return Inertia::render('admin/users/Form', [
            'user' => UserPresenter::detail($user, $request->user()),
            'roles' => UserPresenter::roleOptions($request->user()),
            'statuses' => UserPresenter::statusOptions(),
            'canChangeAccess' => UserPolicy::canChangeAccess($request->user(), $user),
        ]);
    }

    public function update(UserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        Gate::authorize('update', $user);

        $save->update($user, $request->payload(), $request->user());

        $this->toast(['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.show', $user);
    }

    public function status(Request $request, User $user, ChangeUserStatus $change): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate(['status' => ['required', Rule::enum(UserStatus::class)]]);
        $status = UserStatus::from($data['status']);

        $change($user, $status, $request->user());

        $this->toast([
            'type' => 'success',
            'message' => $status === UserStatus::Active ? __('User activated.') : __('User deactivated and signed out.'),
        ]);

        return back();
    }

    public function destroy(Request $request, User $user, DeleteUser $delete): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $delete($user, $request->user());

        $this->toast(['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('restore', $user);

        $user->restore();

        $this->toast(['type' => 'success', 'message' => __('User restored.')]);

        return to_route('admin.users.show', $user);
    }

    /** REQ §11.2: resend the secure account invitation. */
    public function invite(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->notify(new AccountInvitation($request->user()->name));

        $this->toast(['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return back();
    }

    /** REQ §6 "Password Reset": email the user a reset link. */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        $this->toast($status === Password::RESET_LINK_SENT
            ? ['type' => 'success', 'message' => __('Password reset link sent to :email.', ['email' => $user->email])]
            : ['type' => 'error', 'message' => __($status)]);

        return back();
    }

    /** REQ §6 "Email Verification": resend the verification email. */
    public function sendVerification(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($user->hasVerifiedEmail()) {
            $this->toast(['type' => 'info', 'message' => __('This email address is already verified.')]);

            return back();
        }

        $user->sendEmailVerificationNotification();

        $this->toast(['type' => 'success', 'message' => __('Verification email sent to :email.', ['email' => $user->email])]);

        return back();
    }
}
