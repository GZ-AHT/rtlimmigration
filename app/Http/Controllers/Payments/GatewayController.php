<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\GatewayPayments;
use App\Enums\GatewayTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Online payment (REQ §12.3, §12.5 rules 4, 5 and 9).
 *
 * The callback routes are public and CSRF-exempt because the gateway, not
 * our own pages, posts to them. They carry no authority of their own: each
 * one only triggers a server-to-server check with the gateway.
 */
class GatewayController extends Controller
{
    /** The student chooses "Pay online" in the portal. */
    public function start(Request $request, PaymentRequest $paymentRequest, GatewayPayments $payments): SymfonyResponse
    {
        Gate::authorize('pay', $paymentRequest);
        abort_unless($paymentRequest->student_id === $request->user()->student?->id, 403);

        return Inertia::location($payments->start($paymentRequest, $request->user())->redirectUrl);
    }

    /**
     * SSLCommerz Easy Checkout: their script on our page posts here, then
     * opens the returned gateway URL in a popup. The script fixes one
     * endpoint per page, so the invoice arrives in its "cart_json" field;
     * the reply shape (status, data, logo) is the one it expects. It sends
     * the session's CSRF token as "_token", so this route stays protected.
     */
    public function checkout(Request $request, GatewayPayments $payments): JsonResponse
    {
        $cart = json_decode((string) $request->input('cart_json'), true);
        $paymentRequest = PaymentRequest::find(is_array($cart) ? (int) ($cart['invoice'] ?? 0) : 0);

        // Not found and not yours look the same from outside.
        abort_if($paymentRequest === null || $paymentRequest->student_id !== $request->user()->student?->id, 403);
        Gate::authorize('pay', $paymentRequest);

        try {
            $checkout = $payments->start($paymentRequest, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'data' => null, 'message' => collect($e->errors())->flatten()->first()]);
        } catch (\Throwable $e) {
            Log::error('Gateway checkout exception', ['exception' => $e->getMessage()]);

            return response()->json(['status' => 'fail', 'data' => null, 'message' => __('Unable to initiate payment session. Please try again.')]);
        }

        $logo = $checkout->logo;
        if (blank($logo)) {
            $iconPath = public_path('apple-touch-icon.png');
            if (file_exists($iconPath)) {
                $logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents($iconPath));
            } else {
                $logo = asset('apple-touch-icon.png');
            }
        }

        // Their popup always shows a logo; fall back to ours if the store has none.
        return response()->json(['status' => 'success', 'data' => $checkout->redirectUrl, 'logo' => $logo]);
    }

    /** Server-to-server notification from the gateway. */
    public function ipn(Request $request, string $gateway, GatewayPayments $payments): Response
    {
        $payments->settle($gateway, $request->post(), 'ipn');

        // Always 200: the outcome is ours to work out, not the caller's to learn.
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * The payer's browser coming back from the gateway. This arrives as a
     * cross-site POST, so the session cookie is not sent and nobody is
     * logged in here. We settle the transaction server-side, then show a
     * plain page that forwards to the portal, where the session applies.
     */
    public function return(Request $request, string $gateway, string $outcome, GatewayPayments $payments): View
    {
        $payload = $request->post() ?: $request->except(['t']);

        if (blank($payload['tran_id'] ?? null) && is_string($request->query('t'))) {
            // Outcome claims are added only for a failure or cancel return;
            // "success" is never taken on trust.
            $payload = ['tran_id' => $request->query('t'), ...($outcome === 'success' ? [] : ['status' => $outcome === 'cancel' ? 'CANCELLED' : 'FAILED'])];
        }

        $transaction = $payments->settle($gateway, $payload, 'return');

        $state = match (true) {
            $transaction === null => 'unknown',
            $transaction->status === GatewayTransactionStatus::Paid => 'paid',
            $transaction->status === GatewayTransactionStatus::Review => 'review',
            $transaction->status === GatewayTransactionStatus::Cancelled => 'cancelled',
            $transaction->status === GatewayTransactionStatus::Initiated && $outcome === 'success' => 'checking',
            default => 'failed',
        };

        return view('gateway.return', [
            'state' => $state,
            'amount' => $transaction ? Money::format($transaction->amount, $transaction->currency) : null,
            'invoice' => $transaction?->request->invoice_number,
            'next' => route('portal.payments.index'),
        ]);
    }
}
