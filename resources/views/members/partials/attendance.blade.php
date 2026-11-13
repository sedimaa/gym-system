<div class="card overflow-hidden">
    <div class="card-body flex items-center justify-between pb-3">
        <h3 class="card-title">Recent attendance</h3>
        <span class="text-xs text-gray-500">{{ $visitsThisMonth }} {{ Str::plural('visit', $visitsThisMonth) }} this month</span>
    </div>
    @if ($recentAttendance->isEmpty())
        <p class="px-6 pb-6 text-sm text-gray-500">No check-ins yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Time</th><th>Status</th><th>Note</th><th>By</th></tr></thead>
                <tbody>
                    @foreach ($recentAttendance as $a)
                        <tr>
                            <td class="whitespace-nowrap">{{ $a->checked_in_at->format('D, M j, Y') }}</td>
                            <td class="whitespace-nowrap">{{ $a->checked_in_at->format('g:i A') }}</td>
                            <td><span class="{{ $a->status->badgeClass() }}">{{ $a->status->label() }}</span></td>
                            <td class="text-xs text-gray-600">{{ $a->note }}</td>
                            <td class="text-gray-600">{{ $a->recorder->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
