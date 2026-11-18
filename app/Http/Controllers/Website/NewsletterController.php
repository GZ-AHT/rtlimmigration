<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Newsletter sign-up (REQ §21.7 newsletter section, §21.4 footer) and the
 * one-click unsubscribe link. Signing up again re-subscribes; the reply is
 * the same whether or not the address was already listed.
 */
class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return back();
        }

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = Str::lower(trim($data['email']));

        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);

        if (! $subscriber->exists || $subscriber->status !== 'subscribed') {
            $subscriber->fill([
                'status' => 'subscribed',
                'source' => Str::limit((string) $request->input('source', 'website'), 40, ''),
                'ip_address' => $request->ip(),
                'unsubscribe_token' => $subscriber->unsubscribe_token ?: Str::random(48),
                'unsubscribed_at' => null,
            ])->save();
        }

        $this->toast(['type' => 'success', 'message' => __('Thanks for subscribing. Look out for our next update.')]);

        return back();
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->first();

        if ($subscriber && $subscriber->status === 'subscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        $this->toast(['type' => 'success', 'message' => __('You have been unsubscribed.')]);

        return redirect('/');
    }
}
