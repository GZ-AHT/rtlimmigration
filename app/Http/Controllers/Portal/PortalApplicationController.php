<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\Student;
use App\Support\Presenters\ApplicationPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §10.1 "Visa Application" (details and progress) and "Application
 * Status" (stage-by-stage updates); §13.3 chronological timeline. Own
 * records only.
 */
class PortalApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('portal/Applications', [
            'applications' => $this->applications($request)->map(fn (Application $a) => [
                ...ApplicationPresenter::row($a),
                'progress' => ApplicationPresenter::progress($a),
                'stages' => ApplicationPresenter::stages($a),
                'checklist' => ApplicationPresenter::checklist($a),
                'requirements' => ApplicationPresenter::requirements($a),
            ])->values(),
        ]);
    }

    public function status(Request $request): Response
    {
        $url = fn (Document $d) => route('portal.documents.show', $d, false);

        return Inertia::render('portal/ApplicationStatus', [
            'applications' => $this->applications($request)->map(fn (Application $a) => [
                ...ApplicationPresenter::row($a),
                'progress' => ApplicationPresenter::progress($a),
                'history' => $a->history->map(fn ($h) => ApplicationPresenter::history($h, $url, forStudent: true))->values(),
            ])->values(),
        ]);
    }

    /** @return Collection<int, Application> */
    private function applications(Request $request)
    {
        /** @var Student|null $student */
        $student = $request->user()->student;
        abort_if($student === null, 403, __('No student record is linked to your account yet. Please contact your counselor.'));

        return $student->applications()
            ->with(['student', 'visaType.documentCategories', 'visaType.requirements', 'country', 'file', 'workflow.stages', 'stage', 'history.documents'])
            ->get();
    }
}
