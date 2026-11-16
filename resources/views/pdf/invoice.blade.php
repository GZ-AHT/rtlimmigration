@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('pdf._styles')
</head>
<body>
    <div class="footer">{{ $company['name'] }} · Invoice {{ $invoice->invoice_number }} · Generated {{ now()->format('d M Y H:i') }}</div>

    @include('pdf._header', ['heading' => 'INVOICE', 'number' => $invoice->invoice_number])

    <table class="meta">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <strong>{{ $invoice->student->user->name }}</strong><br>
                Student no. {{ $invoice->student->student_number }}<br>
                @if ($invoice->file)File {{ $invoice->file->file_number }}<br>@endif
                <span class="muted">{{ $invoice->student->user->email }}</span>
            </td>
            <td class="right">
                <div class="label">Issued</div>
                {{ $invoice->created_at?->format('d M Y') }}<br>
                <div class="label" style="margin-top: 8px;">Due</div>
                {{ $invoice->due_date?->format('d M Y') ?? 'On receipt' }}<br>
                <div style="margin-top: 10px;"><span class="badge">{{ $invoice->status->label() }}</span></div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Description</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $invoice->type->name }}</strong>
                    @if ($invoice->description)<br><span class="muted">{{ $invoice->description }}</span>@endif
                </td>
                <td class="right">{{ Money::plain($invoice->amount, $invoice->currency) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Invoice total</td><td class="right">{{ Money::plain($invoice->amount, $invoice->currency) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ Money::plain($invoice->amount_paid, $invoice->currency) }}</td></tr>
        @if ($invoice->amount_refunded > 0)
            <tr><td>Refunded</td><td class="right">{{ Money::plain($invoice->amount_refunded, $invoice->currency) }}</td></tr>
        @endif
        <tr class="grand"><td>Balance due</td><td class="right">{{ Money::plain($invoice->outstanding(), $invoice->currency) }}</td></tr>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <div class="label" style="margin-top: 24px;">Payments received</div>
        <table class="lines">
            <thead>
                <tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td>{{ $payment->receipt_number }}</td>
                        <td>{{ $payment->paid_on->format('d M Y') }}</td>
                        <td>{{ $payment->method->name }}</td>
                        <td>{{ $payment->transaction_reference ?? '—' }}</td>
                        <td class="right">{{ Money::plain($payment->amount, $payment->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="note muted">
        Please quote invoice number {{ $invoice->invoice_number }} with your payment.
        A payment counts as received once our Accounts team has confirmed it and issued a receipt.
    </div>
</body>
</html>
