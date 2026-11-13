<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_no }}</title>
    <style>
        @page { margin: 10pt 10pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
        .center { text-align: center; }
        .muted { color: #555; }
        h1 { font-size: 11pt; margin: 4pt 0 2pt; }
        .rule { border-top: 1px dashed #777; margin: 6pt 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1.5pt 0; vertical-align: top; }
        td.r { text-align: right; }
        .total td { font-size: 11pt; font-weight: bold; padding-top: 4pt; }
        img.logo { max-height: 40pt; max-width: 120pt; }
    </style>
</head>
<body>
    <div class="center">
        @if (! empty($logoDataUri))
            <img class="logo" src="{{ $logoDataUri }}" alt="">
        @endif
        <h1>{{ $settings['gym_name'] }}</h1>
        @if ($settings['gym_address'])<div class="muted">{{ $settings['gym_address'] }}</div>@endif
        @if ($settings['gym_phone'])<div class="muted">{{ $settings['gym_phone'] }}</div>@endif
        @if ($settings['receipt_header_note'])<div class="muted">{{ $settings['receipt_header_note'] }}</div>@endif
    </div>

    <div class="rule"></div>
    <table>
        <tr><td>Receipt no.</td><td class="r"><strong>{{ $payment->receipt_no }}</strong></td></tr>
        <tr><td>Date</td><td class="r">{{ $payment->paid_on->format('M j, Y') }} {{ $payment->created_at->format('g:i A') }}</td></tr>
        <tr><td>Member</td><td class="r">{{ $payment->member->full_name }}</td></tr>
        <tr><td>Member code</td><td class="r">{{ $payment->member->member_code }}</td></tr>
    </table>

    <div class="rule"></div>
    <table>
        <tr><td colspan="2"><strong>{{ $payment->plan->name }}</strong> ({{ $payment->kindLabel() }})</td></tr>
        <tr><td>Valid from</td><td class="r">{{ $payment->membership->start_date->format('M j, Y') }}</td></tr>
        <tr><td>Valid until</td><td class="r">{{ $payment->membership->expiry_date->format('M j, Y') }}</td></tr>
        @if ($payment->membership->sessions_total)
            <tr><td>Sessions</td><td class="r">{{ $payment->membership->sessions_total }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="r">{{ \App\Support\Money::peso($payment->amount) }}</td></tr>
        <tr><td>Paid via</td><td class="r">{{ $payment->method->label() }}</td></tr>
        @if ($payment->reference_no)
            <tr><td>Reference no.</td><td class="r">{{ $payment->reference_no }}</td></tr>
        @endif
        <tr><td>Received by</td><td class="r">{{ $payment->receiver->name }}</td></tr>
    </table>

    <div class="rule"></div>
    @if ($settings['receipt_footer'])
        <div class="center">{{ $settings['receipt_footer'] }}</div>
    @endif
    @if ($settings['gym_rules'])
        <div class="muted" style="margin-top:6pt; font-size:7pt; white-space: pre-line;">{{ $settings['gym_rules'] }}</div>
    @endif
</body>
</html>
