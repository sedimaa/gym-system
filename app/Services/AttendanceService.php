<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Front-desk check-in (DESCRIPTION.md → Attendance):
 *  - date and time always come from the server clock
 *  - members whose status allows it are checked in (status "allowed"); a per-session bundle uses one session
 *  - otherwise staff either deny (logged "denied") or override with a required note (logged "manual")
 *  - only the admin adds or corrects records afterwards, always with a note
 */
class AttendanceService
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function checkedInToday(Member $member): ?Attendance
    {
        return $member->attendance()->visits()->onDate(today()->toDateString())->latest('checked_in_at')->first();
    }

    public function checkIn(Member $member, User $by): Attendance
    {
        $status = $this->memberships->status($member);

        if (! $status->allowsCheckIn()) {
            throw new InvalidArgumentException("Membership is {$status->label()}. Deny the check-in or override it with a note.");
        }

        $this->ensureNotAlreadyIn($member);

        return DB::transaction(function () use ($member, $by) {
            $membership = $this->coveringMembership($member);
            $useSession = $membership?->isPerSession() && $membership->sessions_remaining > 0;

            if ($useSession) {
                Membership::whereKey($membership->id)->decrement('sessions_remaining');
            }

            return $member->attendance()->create([
                'membership_id' => $membership?->id,
                'checked_in_at' => now(),
                'status' => AttendanceStatus::Allowed,
                'source' => 'manual',
                'session_used' => $useSession,
                'recorded_by' => $by->id,
            ]);
        });
    }

    public function deny(Member $member, User $by, ?string $note = null): Attendance
    {
        $attendance = $member->attendance()->create([
            'membership_id' => $member->currentMembership?->id,
            'checked_in_at' => now(),
            'status' => AttendanceStatus::Denied,
            'source' => 'manual',
            'note' => $note,
            'recorded_by' => $by->id,
        ]);

        $this->log($attendance, 'denied', "Check-in denied ({$this->memberships->status($member)->label()})", $by);

        return $attendance;
    }

    /** Let in a member whose membership doesn't allow it. A reason is required; no session is used. */
    public function override(Member $member, User $by, string $note): Attendance
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('A note is required to override.');
        }

        $this->ensureNotAlreadyIn($member);

        $status = $this->memberships->status($member);

        $attendance = $member->attendance()->create([
            'membership_id' => $member->currentMembership?->id,
            'checked_in_at' => now(),
            'status' => AttendanceStatus::Manual,
            'source' => 'manual',
            'note' => $note,
            'recorded_by' => $by->id,
        ]);

        $this->log($attendance, 'override', "Check-in override ({$status->label()}): {$note}", $by);

        return $attendance;
    }

    /** Admin: add a missed record (e.g. the PC was off). Never uses a session. */
    public function addRecord(Member $member, CarbonInterface $at, AttendanceStatus $status, string $note, User $by): Attendance
    {
        $attendance = $member->attendance()->create([
            'membership_id' => $member->memberships()->whereDate('start_date', '<=', $at->toDateString())->latest('id')->value('id'),
            'checked_in_at' => $at,
            'status' => $status,
            'source' => 'manual',
            'note' => $note,
            'recorded_by' => $by->id,
        ]);

        $this->log($attendance, 'added', "Attendance added by admin for {$at->format('M j, Y g:i A')}: {$note}", $by);

        return $attendance;
    }

    /** Admin: correct the time or status of a record, with a note. */
    public function correct(Attendance $attendance, CarbonInterface $at, AttendanceStatus $status, string $note, User $by): Attendance
    {
        $old = ['checked_in_at' => $attendance->checked_in_at->toDateTimeString(), 'status' => $attendance->status->value, 'note' => $attendance->note];

        $attendance->update(['checked_in_at' => $at, 'status' => $status, 'note' => $note]);

        $this->log($attendance, 'corrected', "Attendance corrected: {$note}", $by, [
            'old' => $old,
            'attributes' => ['checked_in_at' => $at->toDateTimeString(), 'status' => $status->value, 'note' => $note],
        ]);

        return $attendance;
    }

    /** Admin: delete a record. A session it used is given back. */
    public function delete(Attendance $attendance, User $by): void
    {
        DB::transaction(function () use ($attendance, $by) {
            if ($attendance->session_used && $attendance->membership) {
                Membership::whereKey($attendance->membership_id)->increment('sessions_remaining');
            }

            $this->log($attendance, 'deleted', 'Attendance deleted', $by, ['old' => [
                'member' => $attendance->member->member_code,
                'checked_in_at' => $attendance->checked_in_at->toDateTimeString(),
                'status' => $attendance->status->value,
            ]]);

            $attendance->delete();
        });
    }

    /** The period that covers today (a renewal paid in advance may not have started yet). */
    private function coveringMembership(Member $member): ?Membership
    {
        return $member->memberships()
            ->whereDate('start_date', '<=', today()->toDateString())
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    private function ensureNotAlreadyIn(Member $member): void
    {
        if ($existing = $this->checkedInToday($member)) {
            throw new InvalidArgumentException("Already checked in today at {$existing->checked_in_at->format('g:i A')}.");
        }
    }

    private function log(Attendance $attendance, string $event, string $description, User $by, array $properties = []): void
    {
        activity('attendance')
            ->performedOn($attendance)
            ->causedBy($by)
            ->event($event)
            ->withProperties($properties + ['member' => $attendance->member->member_code])
            ->log($description);
    }
}
