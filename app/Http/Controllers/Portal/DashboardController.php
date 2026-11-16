<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Models\Application;
use App\Models\StudentFile;
use App\Support\Presenters\ApplicationPresenter;
use App\Support\Presenters\PaymentPresenter;
use App\Support\StudentRecords;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §10.1 Student Dashboard overview.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $student = $user->student?->load(['counselor.counselorProfile', 'profile', 'educations', 'files.service', 'files.country', 'paymentRequests.type']);

        return Inertia::render('portal/Dashboard', [
            'student' => $student ? [
                'name' => $user->name,
                'student_number' => $student->student_number,
                'email' => $user->email,
                'phone' => $student->phone,
            ] : null,
            'completeness' => $student ? StudentRecords::completeness($student) : null,
            'files' => $student ? $student->files->map(fn (StudentFile $f) => StudentRecords::fileRow($f))->values() : [],
            'counselor' => $student?->counselor ? [
                'name' => $student->counselor->name,
                'email' => $student->counselor->email,
                'phone' => $student->counselor->counselorProfile?->phone,
            ] : null,
            // REQ §10.1 "Application Progress", "Visa Processing Status".
            'applications' => $student ? $student->applications()->with(['visaType', 'country', 'file', 'workflow.stages', 'stage'])->get()->map(fn (Application $a) => [
                ...ApplicationPresenter::row($a),
                'progress' => ApplicationPresenter::progress($a),
            ])->values() : [],
            'money' => $student ? PaymentPresenter::totals($student->paymentRequests) : null,
            // REQ §10.1 "Recent Activities": the student's latest updates.
            'activity' => $user->notifications()->limit(6)->get()->map(NotificationController::present(...)),
        ]);
    }
}
