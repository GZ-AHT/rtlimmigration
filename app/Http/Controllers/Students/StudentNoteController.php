<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** REQ §10 "Student Notes": internal, staff-only. */
class StudentNoteController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('addNote', $student);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $student->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back();
    }
}
