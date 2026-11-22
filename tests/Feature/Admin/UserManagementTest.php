<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\OtpMail;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

/** A non-Super-Admin who may manage users (custom role). */
function userAdmin(): User
{
    $role = RoleModel::findOrCreate('user-admin', 'web');
    $role->givePermissionTo(['area.admin', 'users.view', 'users.manage']);

    return User::factory()->create()->assignRole('user-admin');
}

function validUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Counselor',
        'email' => 'new.counselor@example.com',
        'status' => 'active',
        'roles' => [Role::Counselor->value],
        'send_invitation' => true,
    ], $overrides);
}

describe('listing', function () {
    it('lists, searches and filters users', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->userWithRole(Role::Counselor)->update(['name' => 'Nadia Counselor']);
        $this->userWithRole(Role::Student)->update(['name' => 'Sam Student']);

        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'nadia']))
            ->assertInertia(fn ($page) => $page->component('admin/users/Index')
                ->where('users.total', 1)
                ->where('users.data.0.name', 'Nadia Counselor'));

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'student']))
            ->assertInertia(fn ($page) => $page->where('users.total', 1)->where('users.data.0.name', 'Sam Student'));
    });

    it('never sends password hashes or 2FA secrets to the page', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page->missing('users.data.0.password')->missing('users.data.0.two_factor_secret'));
    });

    it('is forbidden without users.view', function () {
        $this->actingAs($this->userWithRole(Role::LeadManager))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    });

    it('rejects invalid filter values', function () {
        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->get(route('admin.users.index', ['sort' => 'password']))
            ->assertSessionHasErrors('sort');
    });
});

describe('creating', function () {
    it('creates a user, assigns roles, sends an invitation and logs it', function () {
        Notification::fake();
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->post(route('admin.users.store'), validUser())
            ->assertRedirect();

        $user = User::where('email', 'new.counselor@example.com')->firstOrFail();

        expect($user->hasRole(Role::Counselor->value))->toBeTrue()
            ->and($user->status)->toBe(UserStatus::Active)
            ->and(Activity::where('event', 'roles_updated')->whereMorphedTo('subject', $user)->exists())->toBeTrue();

        Notification::assertSentTo($user, AccountInvitation::class);
    });

    it('can skip the invitation email', function () {
        Notification::fake();

        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->post(route('admin.users.store'), validUser(['send_invitation' => false]));

        Notification::assertNothingSent();
    });

    it('validates input', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->post(route('admin.users.store'), validUser(['email' => 'not-an-email', 'roles' => []]))
            ->assertSessionHasErrors(['email', 'roles']);

        $this->actingAs($admin)->post(route('admin.users.store'), validUser(['email' => $admin->email]))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.users.store'), validUser(['roles' => ['nonexistent-role']]))
            ->assertSessionHasErrors('roles.0');
    });

    it('lets only a Super Admin grant the Super Admin role', function () {
        $this->actingAs(userAdmin())
            ->post(route('admin.users.store'), validUser(['roles' => [Role::SuperAdmin->value]]))
            ->assertSessionHasErrors('roles.0');

        expect(User::where('email', 'new.counselor@example.com')->exists())->toBeFalse();
    });

    it('is forbidden without users.manage', function () {
        $role = RoleModel::findOrCreate('viewer', 'web');
        $role->givePermissionTo(['area.admin', 'users.view']);
        $viewer = User::factory()->create()->assignRole('viewer');

        $this->actingAs($viewer)->post(route('admin.users.store'), validUser())->assertForbidden();
    });
});

describe('updating', function () {
    it('updates profile, roles and status', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $user = $this->userWithRole(Role::Student);

        $this->actingAs($admin)->put(route('admin.users.update', $user), validUser([
            'name' => 'Renamed',
            'email' => $user->email,
            'roles' => [Role::Counselor->value],
            'status' => 'inactive',
        ]))->assertRedirect(route('admin.users.show', $user));

        $user->refresh();

        expect($user->name)->toBe('Renamed')
            ->and($user->getRoleNames()->all())->toBe([Role::Counselor->value])
            ->and($user->status)->toBe(UserStatus::Inactive);
    });

    it('resets verification and emails the new address when the email changes', function () {
        Mail::fake();
        $user = $this->userWithRole(Role::Student);

        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->put(route('admin.users.update', $user), validUser(['email' => 'changed@example.com', 'roles' => [Role::Student->value]]));

        expect($user->refresh()->email_verified_at)->toBeNull();
        Mail::assertQueued(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($user->email));
    });

    it('stops other administrators from editing a Super Admin', function () {
        $super = $this->userWithRole(Role::SuperAdmin);
        $admin = userAdmin();

        $this->actingAs($admin)->get(route('admin.users.edit', $super))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.users.update', $super), validUser(['email' => $super->email]))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $super))->assertForbidden();
    });

    it('does not let users change their own roles or status', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->userWithRole(Role::SuperAdmin); // not the last one

        // Roles/status in the payload are ignored for your own account.
        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Me', 'email' => $admin->email, 'roles' => [Role::Student->value], 'status' => 'inactive',
        ]);

        expect($admin->refresh()->isSuperAdmin())->toBeTrue()->and($admin->isActive())->toBeTrue();

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
    });

    it('protects the last active Super Admin', function () {
        $last = $this->userWithRole(Role::SuperAdmin);
        $other = $this->userWithRole(Role::SuperAdmin);
        $other->update(['status' => UserStatus::Inactive]);

        // $other is inactive, so $last is the only active Super Admin.
        $this->actingAs($other->fresh()->forceFill(['status' => 'active']))
            ->put(route('admin.users.update', $last), validUser(['email' => $last->email, 'roles' => [Role::Counselor->value]]))
            ->assertSessionHasErrors('roles');

        expect($last->refresh()->isSuperAdmin())->toBeTrue();
    });
});

describe('status, deletion and restore', function () {
    it('deactivates a user, signs them out and blocks sign-in', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $user = $this->userWithRole(Role::Counselor);

        $this->actingAs($admin)->patch(route('admin.users.status', $user), ['status' => 'inactive'])->assertRedirect();
        expect($user->refresh()->isActive())->toBeFalse();

        // An existing session is ended on the next request.
        $this->actingAs($user)->get(route('counselor.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // And they can't sign in again.
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('soft-deletes and restores a user', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $user = $this->userWithRole(Role::Student);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        expect(User::find($user->id))->toBeNull()->and(User::withTrashed()->find($user->id))->not->toBeNull();

        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();

        $this->actingAs($admin)->patch(route('admin.users.restore', $user))->assertRedirect(route('admin.users.show', $user));
        expect(User::find($user->id))->not->toBeNull();
    });

    it('cannot delete yourself or the last Super Admin', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');
        expect(User::find($admin->id))->not->toBeNull();
    });

    it('shows a deleted user profile', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $user = $this->userWithRole(Role::Student);
        $user->delete();

        $this->actingAs($admin)->get(route('admin.users.show', $user))
            ->assertInertia(fn ($page) => $page->where('user.deleted', true)->where('user.can.restore', true));
    });
});

describe('account emails', function () {
    it('sends a password reset link and resends a verification code', function () {
        Notification::fake();
        Mail::fake();
        $admin = $this->userWithRole(Role::SuperAdmin);
        $user = User::factory()->unverified()->create()->assignRole(Role::Student->value);

        $this->actingAs($admin)->post(route('admin.users.password-reset', $user))->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);

        $this->actingAs($admin)->post(route('admin.users.verification', $user))->assertRedirect();
        Mail::assertQueued(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($user->email));

        $this->actingAs($admin)->post(route('admin.users.invite', $user))->assertRedirect();
        Notification::assertSentTo($user, AccountInvitation::class);
    });
});
