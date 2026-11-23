<?php

use App\Actions\Users\DeleteUser;
use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('frees the email of a deleted student so it can register again', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $student = Student::factory()->create();
    $user = $student->user;
    $email = $user->email;

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();

    $trashed = User::withTrashed()->find($user->id);
    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->original_email)->toBe($email)
        ->and($trashed->email)->not->toBe($email)
        ->and($trashed->contact_email)->toBe($email)
        // Related records survive.
        ->and(Student::find($student->id))->not->toBeNull();

    auth()->logout();
    $this->post('/register', ['name' => 'Again', 'email' => $email, 'password' => 'Password-12345', 'password_confirmation' => 'Password-12345'])
        ->assertSessionDoesntHaveErrors('email');

    expect(User::where('email', $email)->count())->toBe(1);
});

it('keeps email unique for live accounts', function () {
    $live = User::factory()->create();

    $this->post('/register', ['name' => 'Dup', 'email' => $live->email, 'password' => 'Password-12345', 'password_confirmation' => 'Password-12345'])
        ->assertSessionHasErrors('email');
});

it('restores a deleted user only while the email is still free', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $user = User::factory()->create();
    $email = $user->email;
    app(DeleteUser::class)($user, $admin);

    $this->actingAs($admin)->patch(route('admin.users.restore', $user->id))->assertRedirect();
    expect(User::find($user->id)->email)->toBe($email)->and(User::find($user->id)->original_email)->toBeNull();

    app(DeleteUser::class)(User::find($user->id), $admin);
    User::factory()->create(['email' => $email]);

    $this->actingAs($admin)->patch(route('admin.users.restore', $user->id))->assertSessionHasErrors('user');
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});
