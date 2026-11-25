@php
    use App\Enums\PaymentMethodKind;
    use App\Support\Money;

    $request = $payment->request;
    $profile = $payment->student->profile;
    $kind = $payment->method->kind;
    $date = $payment->confirmed_at ?? $payment->paid_on;

    // Which printed line this payment belongs to (1-8).
    $line = match ($request->type->slug) {
        'university-application-fee' => 1,
        'admission-processing-fee' => 2,
        'visa-processing-fee' => 4,
        'service-charge' => 5,
        default => 8,
    };
    $particulars = [
        1 => 'University / College / School Application', 2 => 'Documentation Fee', 3 => 'Custodian Fee',
        4 => 'Visa Processing Expenses', 5 => 'Service Charges', 6 => 'Tuition / Course / Program Fee',
        7 => 'Residentship / Petition-based',
    ];
    $amount = Money::plain($payment->amount, $payment->currency);
    $img = fn (string $f) => public_path('images/receipt/'.$f);
    $address = collect([$profile?->address_line, $profile?->city, $profile?->country])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Money Receipt {{ $payment->receipt_number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Times-Roman", "Times New Roman", serif; font-size: 11pt; color: #111; }
    .page { position: relative; width: 595pt; height: 838pt; page-break-after: always; overflow: hidden; }
    .page:last-child { page-break-after: auto; }
    .side { position: absolute; left: 0; top: 0; width: 41pt; height: 838pt; }
    .body { position: absolute; left: 50pt; top: 22pt; width: 523pt; }
    table { width: 100%; border-collapse: collapse; }
    .blue { color: #fff; background: #2e3192; font-weight: bold; }
    .pill { background: #e31e24; color: #fff; font-weight: bold; text-align: center; border-radius: 12pt; }
    .box { border: 0.75pt solid #2e3192; height: 18pt; padding: 2pt 6pt; }
    .lbl { padding: 3pt 7pt; height: 20pt; font-size: 9.5pt; }
    .field td { padding: 4pt 0 2pt; font-size: 11.5pt; }
    .field .v { border-bottom: 1pt dotted #444; padding-left: 6pt; }
    .lines td, .lines th { border: 0.75pt solid #2e3192; padding: 0 6pt; font-size: 11pt; height: 21pt; line-height: 1.1; }
    .lines th { background: #2e3192; color: #fff; text-align: center; font-size: 10pt; }
    .r { text-align: right; }
    .c { text-align: center; }
    .sub td { background: #fdf0f3; font-weight: bold; }
</style>
</head>
<body>
<div class="page">
    <img class="side" src="{{ $img('sidebar.png') }}" alt="">
    <div class="body">
        <table>
            <tr>
                <td style="width:100pt;"><img src="{{ $img('logo.png') }}" style="width:100pt;" alt=""></td>
                <td class="c" style="padding-top:2pt;">
                    <div style="font-size:19pt;font-weight:bold;color:#e31e24;">{{ $company['name'] }}</div>
                    <div style="font-size:8.5pt;line-height:1.45;color:#222;">
                        @if ($company['address'])Office Address: {{ $company['address'] }}<br>@endif
                        {{ implode(', ', array_filter([$company['email'] ? 'E-mail: '.$company['email'] : null, $company['phone'] ? 'Tel: '.$company['phone'] : null])) }}
                    </div>
                </td>
                <td style="width:100pt;vertical-align:top;"><div class="pill" style="padding:6pt 4pt;font-size:8.5pt;">APPLICANT’S COPY</div></td>
            </tr>
        </table>

        <table style="margin-top:12pt;">
            <tr>
                <td class="blue lbl" style="width:78pt;">Session/Year</td>
                <td class="box" style="width:90pt;">{{ $date->format('Y') }}</td>
                <td class="c" style="width:200pt;padding:0 10pt;"><div class="pill" style="font-size:14pt;padding:7pt 0;">MONEY RECEIPT</div></td>
                <td class="blue lbl" style="width:52pt;">Country</td>
                <td class="box">{{ $request->file?->country?->name }}</td>
            </tr>
        </table>
        <table style="margin-top:8pt;">
            <tr>
                <td class="blue lbl" style="width:52pt;">SL No.</td>
                <td class="box" style="width:90pt;">{{ $payment->receipt_number }}</td>
                <td class="blue lbl" style="width:98pt;">APPLICANT’S ID</td>
                <td class="box">{{ $payment->student->student_number }}</td>
                <td class="blue lbl" style="width:34pt;">Date</td>
                <td class="box" style="width:96pt;">{{ $date->format('d / m / Y') }}</td>
            </tr>
        </table>

        <table class="field" style="margin-top:8pt;">
            <tr><td style="width:118pt;">Applicant’s Name</td><td style="width:8pt;">:</td><td class="v">{{ $payment->student->user->name }}</td></tr>
            <tr><td>Father’s / Spouse Name</td><td>:</td><td class="v">&nbsp;</td></tr>
            <tr><td>Mother’s Name</td><td>:</td><td class="v">&nbsp;</td></tr>
            <tr><td>Contact Address</td><td>:</td><td class="v">{{ $address ?: '' }}&nbsp;</td></tr>
        </table>
        <table class="field">
            <tr><td class="v" style="width:60%;">&nbsp;</td><td class="r" style="width:80pt;">Contact No :</td><td class="v">{{ $payment->student->phone }}&nbsp;</td></tr>
        </table>

        <table class="lines" style="margin-top:8pt;">
            <tr><th style="width:36pt;">S/L</th><th style="width:34pt;"></th><th>PARTICULARS</th><th style="width:100pt;">AMOUNT</th><th style="width:78pt;">REMARKS</th></tr>
            @foreach ($particulars as $n => $label)
                <tr>
                    <td class="c">{{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</td>
                    @if ($n === 1)<td rowspan="5" class="c" style="color:#e31e24;font-weight:bold;font-size:9pt;">Non-<br>Refundable</td>@elseif ($n > 5)<td></td>@endif
                    <td>{{ $label }}</td>
                    <td class="r">{{ $line === $n ? $amount : '' }}</td>
                    <td style="font-size:8pt;">{{ $line === $n ? $request->invoice_number : "" }}</td>
                </tr>
            @endforeach
            <tr><td rowspan="3"></td><td rowspan="3"></td><td class="r">Assessment :</td><td></td><td></td></tr>
            <tr><td class="r">Case Fee :</td><td></td><td></td></tr>
            <tr><td class="r">Settlement Fee :</td><td></td><td></td></tr>
            <tr><td class="c">08</td><td></td><td>Others{{ $line === 8 ? ' — '.$request->type->name : '' }}</td><td class="r">{{ $line === 8 ? $amount : '' }}</td><td>{{ $line === 8 ? $request->invoice_number : '' }}</td></tr>
            <tr class="sub"><td></td><td></td><td class="r">Sub Total</td><td class="r">{{ $amount }}</td><td></td></tr>
        </table>

        <table class="field" style="margin-top:10pt;">
            <tr><td style="width:100pt;">Amount in words :</td><td class="v">{{ Money::words($payment->amount, $payment->currency) }}</td></tr>
        </table>
        <table class="field">
            <tr>
                <td style="width:92pt;">Payment Method :</td>
                <td style="width:90pt;">Cash {{ $kind === PaymentMethodKind::Cash ? '[ X ]' : '[  ]' }}</td>
                <td style="width:90pt;">Bank {{ in_array($kind, [PaymentMethodKind::BankTransfer, PaymentMethodKind::Card], true) ? '[ X ]' : '[  ]' }}</td>
                <td style="width:100pt;">Cheque / Ref. No.</td>
                <td class="v">{{ $payment->transaction_reference }}&nbsp;</td>
            </tr>
        </table>
        <table class="field">
            <tr><td style="width:36pt;">Date :</td><td class="v" style="width:130pt;">{{ $payment->paid_on->format('d M Y') }}</td><td style="width:78pt;text-align:right;">Special Note :</td><td class="v">{{ $payment->notes }}&nbsp;</td></tr>
        </table>

        <p style="margin:10pt 0 0;font-size:10.5pt;line-height:1.5;">Before signing this money receipt, both parties agree to comply with the general terms and conditions printed on the overleaf.</p>

        <table style="margin-top:26pt;">
            <tr>
                <td class="c" style="width:240pt;"><div style="border-top:1pt solid #333;padding-top:3pt;font-weight:bold;">Applicant / Guardian's Signature</div><div style="color:#e31e24;font-weight:bold;font-size:9pt;">(NID / Passport)</div></td>
                <td class="c" style="width:60pt;"><img src="{{ $img('stamp.png') }}" style="width:58pt;" alt=""></td>
                <td class="c"><div style="border-top:1pt solid #333;padding-top:3pt;font-weight:bold;">Asst. Manager / Manager (Accounts)</div>@if ($payment->confirmer)<div style="font-size:8.5pt;color:#444;">{{ $payment->confirmer->name }}</div>@endif</td>
            </tr>
        </table>
    </div>
</div>

<div class="page">
    <img src="{{ $img('terms.png') }}" style="width:595pt;height:838pt;" alt="General Terms &amp; Condition">
</div>
</body>
</html>
