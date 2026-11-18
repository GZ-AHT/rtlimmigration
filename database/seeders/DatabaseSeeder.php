<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\Counselor;
use App\Models\Student;
use App\Models\User;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            ReferenceDataSeeder::class,
            WebsiteContentSeeder::class,
            SkillAssessmentSeeder::class,
        ]);

        // Local demo accounts, one per role. Never created in production.
        if (app()->isLocal()) {
            foreach (Role::cases() as $role) {
                $user = User::firstOrCreate(
                    ['email' => "{$role->value}@example.com"],
                    ['name' => "Demo {$role->label()}", 'password' => 'password'],
                );

                $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
                $user->syncRoles([$role->value]);

                if ($role === Role::Counselor) {
                    Counselor::firstOrCreate(['user_id' => $user->id], ['designation' => 'Senior Counselor']);
                }

                if ($role === Role::Student && ! $user->student) {
                    Student::create([
                        'user_id' => $user->id,
                        'student_number' => app(NumberGenerator::class)->next('student'),
                        'source' => StudentSource::Admin,
                    ]);
                }
            }
        }
    }
}
