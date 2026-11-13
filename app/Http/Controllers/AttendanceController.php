<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\AttendanceRecordRequest;
use App\Http\Requests\CheckInRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Walkin;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly MembershipService $memberships,
    ) {}

    /** Check-in desk plus the day's log (members and walk-ins). */
    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', Attendance::class);

        $input = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'q' => ['nullable', 'string', 'max:100'],
            'member' => ['nullable', 'integer'],
        ]);
        $date = $input['date'] ?? today()->toDateString();

        // Search: an exact member code (or a single match) selects the member right away.
        $matches = collect();
        if (filled($input['q'] ?? null)) {
            $exact = Member::where('member_code', strtoupper(trim($input['q'])))->first();
            $matches = $exact ? collect([$exact]) : Member::search($input['q'])->with('currentMembership.plan')->orderBy('last_name')->limit(12)->get();

            if ($matches->count() === 1) {
                return redirect()->route('attendance.index', array_filter(['member' => $matches->first()->id, 'date' => $input['date'] ?? null]));
            }
        }

        $selected = isset($input['member']) ? Member::with('currentMembership.plan')->find($input['member']) : null;

        $records = Attendance::with(['member', 'recorder'])->onDate($date)->get()
            ->map(fn (Attendance $a) => ['type' => 'member', 'at' => $a->checked_in_at, 'record' => $a]);
        $walkins = Walkin::with('recorder')->onDate($date)->get()
            ->map(fn (Walkin $w) => ['type' => 'walkin', 'at' => $w->visited_at, 'record' => $w]);

        $memberVisits = $records->filter(fn ($r) => $r['record']->status->isVisit())->count();

        return view('attendance.index', [
            'date' => $date,
            'q' => $input['q'] ?? '',
            'matches' => $matches,
            'selected' => $selected,
            'selectedStatus' => $selected ? $this->memberships->status($selected) : null,
            'alreadyIn' => $selected ? $this->attendance->checkedInToday($selected) : null,
            'log' => $records->concat($walkins)->sortByDesc('at')->values(),
            'totals' => [
                'members' => $memberVisits,
                'walkins' => $walkins->count(),
                'total' => $memberVisits + $walkins->count(),
                'denied' => $records->filter(fn ($r) => $r['record']->status === AttendanceStatus::Denied)->count(),
            ],
        ]);
    }

    /** Front desk: allow, deny or override (with note). Time = server clock. */
    public function store(CheckInRequest $request): RedirectResponse
    {
        $member = Member::with('currentMembership')->findOrFail($request->validated('member_id'));

        try {
            match ($request->validated('action')) {
                'allow' => $this->attendance->checkIn($member, $request->user()),
                'deny' => $this->attendance->deny($member, $request->user(), $request->validated('note')),
                'override' => $this->attendance->override($member, $request->user(), $request->validated('note')),
            };
        } catch (InvalidArgumentException $e) {
            return redirect()->route('attendance.index', ['member' => $member->id])->with('error', $e->getMessage());
        }

        $message = match ($request->validated('action')) {
            'allow' => "{$member->full_name} checked in.",
            'deny' => "Check-in denied for {$member->full_name}.",
            'override' => "{$member->full_name} let in (override noted).",
        };

        return redirect()->route('attendance.index')->with('success', $message);
    }

    public function create(Request $request): View
    {
        Gate::authorize('addRecord', Attendance::class);

        return view('attendance.form', [
            'attendance' => null,
            'member' => $request->filled('member') ? Member::find($request->integer('member')) : null,
            'members' => Member::orderBy('last_name')->orderBy('first_name')->get(['id', 'member_code', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function storeRecord(AttendanceRecordRequest $request): RedirectResponse
    {
        $member = Member::findOrFail($request->validated('member_id'));
        $at = Carbon::parse($request->validated('checked_in_at'));

        $this->attendance->addRecord($member, $at, AttendanceStatus::from($request->validated('status')), $request->validated('note'), $request->user());

        return redirect()->route('attendance.index', ['date' => $at->toDateString()])->with('success', 'Attendance record added.');
    }

    public function edit(Attendance $attendance): View
    {
        Gate::authorize('update', $attendance);

        return view('attendance.form', [
            'attendance' => $attendance->load('member'),
            'member' => $attendance->member,
            'members' => collect(),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function update(AttendanceRecordRequest $request, Attendance $attendance): RedirectResponse
    {
        $at = Carbon::parse($request->validated('checked_in_at'));

        $this->attendance->correct($attendance, $at, AttendanceStatus::from($request->validated('status')), $request->validated('note'), $request->user());

        return redirect()->route('attendance.index', ['date' => $at->toDateString()])->with('success', 'Attendance record corrected.');
    }

    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        Gate::authorize('delete', $attendance);

        $date = $attendance->checked_in_at->toDateString();
        $this->attendance->delete($attendance, $request->user());

        return redirect()->route('attendance.index', ['date' => $date])->with('success', 'Attendance record deleted.');
    }
}
