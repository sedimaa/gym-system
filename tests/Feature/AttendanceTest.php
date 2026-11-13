<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Plan $monthly;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 07:15:00');
        $this->monthly = Plan::factory()->monthly()->create(['name' => 'Monthly']);
        $this->staff = User::factory()->staff()->create();
    }

    private function memberExpiring(string $expiry, array $membership = [], array $member = []): Member
    {
        $m = Member::factory()->create($member);
        $m->memberships()->create(array_merge([
            'plan_id' => $this->monthly->id,
            'start_date' => Carbon::parse($expiry)->subDays(30),
            'expiry_date' => $expiry,
        ], $membership));

        return $m;
    }

    private function act(Member $member, string $action, ?string $note = null)
    {
        return $this->actingAs($this->staff)->post(route('attendance.store'), array_filter([
            'member_id' => $member->id, 'action' => $action, 'note' => $note,
        ]));
    }

    public function test_active_member_checks_in_with_server_time(): void
    {
        $member = $this->memberExpiring('2026-12-01');

        $this->act($member, 'allow')->assertRedirect(route('attendance.index'))->assertSessionHas('success');

        $a = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Allowed, $a->status);
        $this->assertSame('manual', $a->source);
        $this->assertSame('2026-11-14 07:15:00', $a->checked_in_at->format('Y-m-d H:i:s'));
        $this->assertTrue($a->recorder->is($this->staff));
    }

    public function test_check_in_uses_one_session_of_a_bundle(): void
    {
        $member = $this->memberExpiring('2026-12-20', ['sessions_total' => 10, 'sessions_remaining' => 3, 'start_date' => '2026-11-01']);

        $this->act($member, 'allow');

        $this->assertSame(2, $member->currentMembership()->first()->sessions_remaining);
        $this->assertTrue(Attendance::first()->session_used);
    }

    public function test_expired_member_shows_red_warning_and_cannot_simply_check_in(): void
    {
        $member = $this->memberExpiring('2026-11-10');

        $this->actingAs($this->staff)->get(route('attendance.index', ['member' => $member->id]))
            ->assertOk()->assertSee('Not allowed to check in')->assertSee('Membership expired on Nov 10, 2026')
            ->assertSee('Deny entry')->assertSee('Override');

        $this->act($member, 'allow')->assertSessionHas('error');
        $this->assertSame(0, Attendance::count());
    }

    public function test_deny_is_logged_as_denied(): void
    {
        $member = $this->memberExpiring('2026-11-10');

        $this->act($member, 'deny');

        $this->assertSame(AttendanceStatus::Denied, Attendance::first()->status);
        $this->assertTrue(Activity::where('log_name', 'attendance')->where('event', 'denied')->exists());
    }

    public function test_override_requires_a_note_and_is_logged_as_manual(): void
    {
        $member = $this->memberExpiring('2026-11-10');

        $this->act($member, 'override')->assertSessionHasErrors('note');
        $this->assertSame(0, Attendance::count());

        $this->act($member, 'override', 'Will pay tomorrow');

        $a = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Manual, $a->status);
        $this->assertSame('Will pay tomorrow', $a->note);
        $log = Activity::where('log_name', 'attendance')->where('event', 'override')->firstOrFail();
        $this->assertTrue($log->causer->is($this->staff));
    }

    public function test_deactivated_and_no_plan_members_cannot_check_in_normally(): void
    {
        $inactive = $this->memberExpiring('2026-12-31', [], ['is_active' => false]);
        $none = Member::factory()->create();

        $this->act($inactive, 'allow')->assertSessionHas('error');
        $this->act($none, 'allow')->assertSessionHas('error');
        $this->assertSame(0, Attendance::count());
    }

    public function test_second_check_in_on_the_same_day_is_refused(): void
    {
        $member = $this->memberExpiring('2026-12-20', ['sessions_total' => 10, 'sessions_remaining' => 5, 'start_date' => '2026-11-01']);

        $this->act($member, 'allow');
        $this->act($member, 'allow')->assertSessionHas('error');

        $this->assertSame(1, Attendance::count());
        $this->assertSame(4, $member->currentMembership()->first()->sessions_remaining);
    }

    public function test_searching_an_exact_member_code_selects_the_member(): void
    {
        $member = $this->memberExpiring('2026-12-01');

        $this->actingAs($this->staff)->get(route('attendance.index', ['q' => strtolower($member->member_code)]))
            ->assertRedirect(route('attendance.index', ['member' => $member->id]));
    }

    public function test_daily_log_counts_members_and_walk_ins_separately(): void
    {
        $member = $this->memberExpiring('2026-12-01');
        $expired = $this->memberExpiring('2026-11-01');
        Plan::factory()->dayPass()->create();

        $this->act($member, 'allow');
        $this->act($expired, 'deny');
        $this->actingAs($this->staff)->post(route('walkins.store'), ['name' => 'Bisita', 'method' => 'cash']);

        $this->get(route('attendance.index'))->assertOk()
            ->assertViewHas('totals', ['members' => 1, 'walkins' => 1, 'total' => 2, 'denied' => 1])
            ->assertSee('Bisita')->assertSee($member->full_name);
    }

    public function test_staff_cannot_add_correct_or_delete_records(): void
    {
        $member = $this->memberExpiring('2026-12-01');
        $this->act($member, 'allow');
        $a = Attendance::first();

        $this->actingAs($this->staff);
        $this->get(route('attendance.create'))->assertForbidden();
        $this->post(route('attendance.records.store'), ['member_id' => $member->id, 'checked_in_at' => '2026-11-13 08:00', 'status' => 'manual', 'note' => 'x'])->assertForbidden();
        $this->get(route('attendance.edit', $a))->assertForbidden();
        $this->put(route('attendance.update', $a), ['checked_in_at' => '2026-11-14 06:00', 'status' => 'allowed', 'note' => 'x'])->assertForbidden();
        $this->delete(route('attendance.destroy', $a))->assertForbidden();

        $this->assertSame(1, Attendance::count());
    }

    public function test_admin_adds_and_corrects_records_with_a_required_note(): void
    {
        $admin = User::factory()->admin()->create();
        $member = $this->memberExpiring('2026-12-01');
        $this->actingAs($admin);

        $this->get(route('attendance.create'))->assertOk();
        $this->post(route('attendance.records.store'), ['member_id' => $member->id, 'checked_in_at' => '2026-11-13 08:00', 'status' => 'manual'])
            ->assertSessionHasErrors('note');
        $this->post(route('attendance.records.store'), ['member_id' => $member->id, 'checked_in_at' => '2026-11-13 08:00', 'status' => 'manual', 'note' => 'Paper log'])
            ->assertSessionHasNoErrors();

        $a = Attendance::firstOrFail();
        $this->assertSame('2026-11-13 08:00:00', $a->checked_in_at->format('Y-m-d H:i:s'));
        $this->assertFalse($a->session_used);

        $this->get(route('attendance.edit', $a))->assertOk();
        $this->put(route('attendance.update', $a), ['checked_in_at' => '2026-11-13 09:30', 'status' => 'allowed', 'note' => 'Wrong time'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09:30', $a->fresh()->checked_in_at->format('H:i'));

        $log = Activity::where('log_name', 'attendance')->where('event', 'corrected')->firstOrFail();
        $this->assertSame('2026-11-13 08:00:00', $log->properties['old']['checked_in_at']);
        $this->assertTrue($log->causer->is($admin));
    }

    public function test_deleting_a_check_in_gives_the_session_back(): void
    {
        $member = $this->memberExpiring('2026-12-20', ['sessions_total' => 10, 'sessions_remaining' => 5, 'start_date' => '2026-11-01']);
        $this->act($member, 'allow');

        $this->actingAs(User::factory()->admin()->create())->delete(route('attendance.destroy', Attendance::first()));

        $this->assertSame(0, Attendance::count());
        $this->assertSame(5, $member->currentMembership()->first()->sessions_remaining);
    }

    public function test_member_profile_shows_attendance(): void
    {
        $member = $this->memberExpiring('2026-12-01');
        $this->act($member, 'allow');

        $this->get(route('members.show', $member))->assertOk()->assertSee('Recent attendance')->assertSee('7:15 AM')->assertSee('1 visit this month');
    }
}
