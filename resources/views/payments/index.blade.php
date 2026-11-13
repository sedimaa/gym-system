<x-app-layout>
    <x-slot name="title">Payments</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Payments</h1>
    </x-slot>

    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
        <form method="GET" class="grid gap-2 sm:grid-cols-2 lg:flex lg:items-end">
            <div><label class="form-label" for="from">From</label><input id="from" type="date" name="from" value="{{ $from }}" class="form-input"></div>
            <div><label class="form-label" for="to">To</label><input id="to" type="date" name="to" value="{{ $to }}" class="form-input"></div>
            <div>
                <label class="form-label" for="method">Paid via</label>
                <select id="method" name="method" class="form-select">
                    <option value="">All</option>
                    @foreach ($methods as $m)
                        <option value="{{ $m->value }}" @selected(($filters['method'] ?? '') === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label" for="q">Member / receipt</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="Name, code or OR no."></div>
            <button class="btn-secondary">Filter</button>
        </form>
        <a href="{{ route('payments.create') }}" class="btn-primary">+ Record payment</a>
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card card-body"><p class="stat-label">Total collected</p><p class="stat-value">@peso($total)</p><p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($from)->format('M j') }} – {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}</p></div>
        <div class="card card-body"><p class="stat-label">Payments</p><p class="stat-value">{{ number_format($count) }}</p><p class="text-xs text-gray-500">Member payments only; walk-ins are under Walk-ins</p></div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Receipt</th><th>Date</th><th>Member</th><th>Plan</th><th class="text-right">Amount</th><th>Paid via</th><th>Received by</th></tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td><a href="{{ route('payments.show', $payment) }}" class="link font-mono text-xs">{{ $payment->receipt_no }}</a></td>
                            <td class="whitespace-nowrap">{{ $payment->paid_on->format('M j, Y') }}</td>
                            <td><a href="{{ route('members.show', $payment->member) }}" class="hover:underline">{{ $payment->member->full_name }}</a></td>
                            <td>{{ $payment->plan->name }} <span class="text-xs text-gray-500">· {{ $payment->kindLabel() }}</span></td>
                            <td class="text-right font-medium tabular-nums">@peso($payment->amount)</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td class="text-gray-600">{{ $payment->receiver->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-500">No payments in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-app-layout>
