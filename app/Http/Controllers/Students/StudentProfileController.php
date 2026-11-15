<?php

namespace App\Http\Controllers\Students;

use App\Actions\Students\UpdateStudentProfile;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentProfileRequest;
use App\Models\Student;
use App\Support\StudentRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Staff editing a student's profile (REQ §8.1 "Student Profile Update", §10). */
class StudentProfileController extends Controller
{
    use ResolvesAreaRoutes;

    public function edit(Request $request, Student $student): Response
    {
        Gate::authorize('updateProfile', $student);

        $student->load(['user', 'profile', 'educations']);

        return Inertia::render('students/Profile', [
            'student' => ['id' => $student->id, 'name' => $student->user->name, 'student_number' => $student->student_number],
            'profile' => StudentRecords::profileForm($student),
            'options' => StudentRecords::profileOptions(),
            'urls' => [
                'submit' => $this->areaRoute($request, 'students.profile.update', $student),
                'cancel' => $this->areaRoute($request, 'students.show', ['student' => $student, 'tab' => 'profile']),
                'index' => $this->areaRoute($request, 'students.index'),
            ],
        ]);
    }

    public function update(StudentProfileRequest $request, Student $student, UpdateStudentProfile $update): RedirectResponse
    {
        Gate::authorize('updateProfile', $student);

        $update($student, $request->validated('phone'), $request->profile(), $request->educations());

        $this->toast(['type' => 'success', 'message' => __('Profile saved.')]);

        return redirect($this->areaRoute($request, 'students.show', ['student' => $student, 'tab' => 'profile']));
    }
}
