<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Addresses that opted out of bulk email (REQ §3 Bulk Email). People unsubscribe
 * themselves from the link in each email; an administrator can also add
 * someone who asked another way (a phone call, a reply), or put an address
 * back if they asked to hear from us again. Permission: email.manage.
 */
class EmailSuppressionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('email.manage');

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/email/Unsubscribed', [
            'addresses' => EmailSuppression::query()
                ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.strtolower($search).'%'))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (EmailSuppression $s) => [
                    'id' => $s->id,
                    'email' => $s->email,
                    'reason' => $s->reason === 'unsubscribed' ? __('Used the unsubscribe link') : __('Added by an administrator'),
                    'created_at' => $s->created_at->toIso8601String(),
                ]),
            'filters' => ['search' => $search],
            'total' => EmailSuppression::query()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = strtolower(trim($data['email']));

        if (EmailSuppression::query()->where('email', $email)->exists()) {
            $this->toast(['type' => 'success', 'message' => __(':email is already unsubscribed.', ['email' => $email])]);

            return back();
        }

        EmailSuppression::add($email, 'admin');
        activity('email')->causedBy($request->user())->withProperties(['email' => $email])->log('Address added to the bulk email opt-out list');
        $this->toast(['type' => 'success', 'message' => __(':email will no longer get bulk email.', ['email' => $email])]);

        return back();
    }

    public function destroy(Request $request, EmailSuppression $suppression): RedirectResponse
    {
        Gate::authorize('email.manage');

        $email = $suppression->email;
        $suppression->delete();
        activity('email')->causedBy($request->user())->withProperties(['email' => $email])->log('Address removed from the bulk email opt-out list');
        $this->toast(['type' => 'success', 'message' => __(':email can get bulk email again.', ['email' => $email])]);

        return back();
    }
}
