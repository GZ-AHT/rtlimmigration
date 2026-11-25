<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Student;
use App\Policies\StudentPolicy;
use App\Support\Settings\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable invoices and receipts (REQ §12.5 rule 12, §9.1 "Student
 * Invoices" and "Payment Receipts"). Mounted in all four areas; the
 * policies decide who may open which document.
 */
class PaymentDocumentController extends Controller
{
    public function invoice(PaymentRequest $paymentRequest, SettingsRepository $settings): Response
    {
        Gate::authorize('view', $paymentRequest);

        $paymentRequest->load(['student.user', 'student.profile', 'type', 'file.country', 'requester', 'payments' => fn ($q) => $q->where('status', PaymentStatus::Confirmed->value)->with('method')]);

        return Pdf::loadView('pdf.invoice', ['invoice' => $paymentRequest, 'company' => self::company($settings), 'copy' => self::copyLabel($paymentRequest->student)])
            ->setPaper('a4')
            ->download($paymentRequest->invoice_number.'.pdf');
    }

    public function receipt(Payment $payment, SettingsRepository $settings): Response
    {
        Gate::authorize('view', $payment);
        // A receipt exists only once the money is confirmed.
        abort_unless($payment->status === PaymentStatus::Confirmed && $payment->receipt_number !== null, 404);

        $payment->load(['request.type', 'request.file.country', 'student.user', 'student.profile', 'method', 'confirmer']);

        return Pdf::loadView('pdf.receipt', ['payment' => $payment, 'company' => self::company($settings), 'copy' => self::copyLabel($payment->student)])
            ->setPaper('a4')
            ->download($payment->receipt_number.'.pdf');
    }

    /** The student gets the applicant's copy, the Super Admin the office copy, other staff (counselor, Accounts) the file copy. */
    private static function copyLabel(Student $student): string
    {
        $user = auth()->user();

        return match (true) {
            StudentPolicy::isOwner($user, $student) => 'APPLICANT’S COPY',
            $user->isSuperAdmin() => 'OFFICE COPY',
            default => 'FILE COPY',
        };
    }

    /** @return array{name: string, tagline: string|null, address: string|null, phone: string|null, email: string|null} */
    public static function company(SettingsRepository $settings): array
    {
        return [
            'name' => (string) $settings->get('general.site_name', config('app.name')),
            'tagline' => $settings->get('general.tagline'),
            'address' => $settings->get('general.address'),
            'phone' => $settings->get('general.phone'),
            'email' => $settings->get('general.contact_email'),
        ];
    }
}
