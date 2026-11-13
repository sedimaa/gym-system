<x-app-layout>
    <x-slot name="title">Payment {{ $payment->receipt_no }}</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Payment {{ $payment->receipt_no }}</h1>
    </x-slot>

    <div class="card card-body max-w-2xl">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="stat-label">Amount paid</p>
                <p class="text-3xl font-semibold">@peso($payment->amount)</p>
                <p class="text-sm text-gray-600">{{ $payment->method->label() }}@if ($payment->reference_no) · Ref. {{ $payment->reference_no }}@endif</p>
            </div>
            <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn-primary">Print receipt</a>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Member</dt><dd><a href="{{ route('members.show', $payment->member) }}" class="link">{{ $payment->member->full_name }}</a> <span class="font-mono text-xs text-gray-500">{{ $payment->member->member_code }}</span></dd></div>
            <div><dt class="text-gray-500">Plan</dt><dd>{{ $payment->plan->name }} <span class="badge-slate ml-1">{{ $payment->kindLabel() }}</span></dd></div>
            <div><dt class="text-gray-500">Coverage</dt><dd>{{ $payment->membership->start_date->format('M j, Y') }} → {{ $payment->membership->expiry_date->format('M j, Y') }}</dd></div>
            @if ($payment->membership->sessions_total)
                <div><dt class="text-gray-500">Sessions</dt><dd>{{ $payment->membership->sessions_remaining }} of {{ $payment->membership->sessions_total }} left</dd></div>
            @endif
            <div><dt class="text-gray-500">Payment date</dt><dd>{{ $payment->paid_on->format('M j, Y') }} <span class="text-gray-500">(saved {{ $payment->created_at->format('M j, g:i A') }})</span></dd></div>
            <div><dt class="text-gray-500">Received by</dt><dd>{{ $payment->receiver->name }}</dd></div>
            @if ($payment->notes)
                <div class="sm:col-span-2"><dt class="text-gray-500">Notes</dt><dd>{{ $payment->notes }}</dd></div>
            @endif
        </dl>

        <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
            <a href="{{ route('members.show', $payment->member) }}" class="link text-sm">← Back to member</a>
            @can('delete', $payment)
                @if ($canDelete)
                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="ml-auto"
                          onsubmit="return confirm({{ Js::from('Delete payment '.$payment->receipt_no.'? The membership period it created will be removed too.') }});">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link-danger text-sm">Delete payment</button>
                    </form>
                @else
                    <span class="ml-auto text-xs text-gray-500">Only the member's latest payment can be deleted.</span>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
