<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\AssignLead;
use App\Actions\Leads\ConvertLeadToStudent;
use App\Actions\Leads\RecordLeadActivity;
use App\Enums\ContactChannel;
use App\Enums\LeadStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Actions on a single lead: assign / transfer, status, notes, contacts, follow-ups, convert (REQ §7). */
class LeadActionController extends Controller
{
    use ResolvesAreaRoutes;

    public function assign(Request $request, Lead $lead, AssignLead $assign): RedirectResponse
    {
        $data = $request->validate([
            'counselor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $counselor = isset($data['counselor_id']) ? User::findOrFail((int) $data['counselor_id']) : null;

        // Moving a lead between counselors is a transfer and needs its own permission.
        Gate::authorize($lead->counselor_id !== null && $counselor !== null ? 'transfer' : 'assign', $lead);

        $assign($lead, $counselor, $request->user(), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => $counselor
            ? __('Lead assigned to :name.', ['name' => $counselor->name])
            : __('Lead unassigned.')]);

        return back();
    }

    public function status(Request $request, Lead $lead, RecordLeadActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['status' => ['required', Rule::enum(LeadStatus::class)]]);
        $activity->changeStatus($lead, LeadStatus::from($data['status']));

        $this->toast(['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }

    public function note(Request $request, Lead $lead, RecordLeadActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $activity->addNote($lead, $request->user(), $data['body']);

        return back();
    }

    public function contact(Request $request, Lead $lead, RecordLeadActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'channel' => ['required', Rule::enum(ContactChannel::class)],
            'summary' => ['required', 'string', 'max:5000'],
            'contacted_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $activity->logContact(
            $lead,
            $request->user(),
            ContactChannel::from($data['channel']),
            $data['summary'],
            isset($data['contacted_at']) ? CarbonImmutable::parse($data['contacted_at']) : CarbonImmutable::now(),
        );

        $this->toast(['type' => 'success', 'message' => __('Contact recorded.')]);

        return back();
    }

    public function scheduleFollowUp(Request $request, Lead $lead, RecordLeadActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'due_at' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $activity->scheduleFollowUp($lead, $request->user(), CarbonImmutable::parse($data['due_at']), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('Follow-up scheduled.')]);

        return back();
    }

    public function completeFollowUp(Request $request, Lead $lead, LeadFollowUp $followUp, RecordLeadActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $lead);
        abort_unless($followUp->lead_id === $lead->id, 404);

        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:255']]);
        $activity->completeFollowUp($followUp, $request->user(), $data['outcome'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('Follow-up completed.')]);

        return back();
    }

    public function convert(Request $request, Lead $lead, ConvertLeadToStudent $convert): RedirectResponse
    {
        Gate::authorize('convert', $lead);

        $data = $request->validate(['send_invitation' => ['sometimes', 'boolean']]);
        $student = $convert($lead, $request->user(), $data['send_invitation'] ?? true);

        $this->toast(['type' => 'success', 'message' => __('Converted to student :number.', ['number' => $student->student_number])]);

        return $this->areaHas($request, 'students.show') && $request->user()->can('view', $student)
            ? redirect($this->areaRoute($request, 'students.show', $student))
            : back();
    }
}
