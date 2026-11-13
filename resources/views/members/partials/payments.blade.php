<div class="card overflow-hidden">
    <div class="card-body pb-3"><h3 class="card-title">Payments</h3></div>
    @if ($payments->isEmpty())
        <p class="px-6 pb-6 text-sm text-gray-500">No payments yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Plan</th><th class="text-right">Amount</th><th>Paid via</th><th>Received by</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td><a href="{{ route('payments.show', $payment) }}" class="link font-mono text-xs">{{ $payment->receipt_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $payment->paid_on->format('M j, Y') }}</td>
                            <td>{{ $payment->plan->name }} <span class="text-xs text-gray-500">· {{ $payment->kindLabel() }}</span></td>
                            <td class="text-right font-medium tabular-nums">@peso($payment->amount)</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td class="text-gray-600">{{ $payment->receiver->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
