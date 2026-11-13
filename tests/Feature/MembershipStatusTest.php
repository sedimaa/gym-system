<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MembershipService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MembershipStatusTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 10:00:00');
        $this->plan = Plan::factory()->monthly()->create();
    }

    private function service(): MembershipService
    {
        return app(MembershipService::class);
    }

    /** Member whose current membership expires $days from today. */
    private function memberExpiringIn(int $days, array $membership = [], array $member = []): Member
    {
        $m = Member::factory()->create($member);
        $m->memberships()->create(array_merge([
            'plan_id' => $this->plan->id,
            'start_date' => today()->addDays($days - 30),
            'expiry_date' => today()->addDays($days),
        ], $membership));

        return $m->fresh();
    }

    private function statusOf(Member $member): MembershipStatus
    {
        return $this->service()->status($member);
    }

    public function test_active_when_expiry_is_beyond_the_expiring_soon_window(): void
    {
        $this->assertSame(MembershipStatus::Active, $this->statusOf($this->memberExpiringIn(8)));
        $this->assertSame(MembershipStatus::Active, $this->statusOf($this->memberExpiringIn(25)));
    }

    public function test_expiring_within_seven_days_including_today(): void
    {
        $this->assertSame(MembershipStatus::Expiring, $this->statusOf($this->memberExpiringIn(7)));
        $this->assertSame(MembershipStatus::Expiring, $this->statusOf($this->memberExpiringIn(1)));
        $this->assertSame(MembershipStatus::Expiring, $this->statusOf($this->memberExpiringIn(0)));
    }

    public function test_expired_the_day_after_expiry_when_there_is_no_grace(): void
    {
        $this->assertSame(MembershipStatus::Expired, $this->statusOf($this->memberExpiringIn(-1)));
        $this->assertSame(MembershipStatus::Expired, $this->statusOf($this->memberExpiringIn(-40)));
    }

    public function test_grace_days_delay_expiry(): void
    {
        app(Settings::class)->set(['grace_days' => 3]);

        $this->assertSame(MembershipStatus::Expiring, $this->statusOf($this->memberExpiringIn(-3)), 'last grace day');
        $this->assertSame(MembershipStatus::Expired, $this->statusOf($this->memberExpiringIn(-4)), 'grace over');
    }

    public function test_expiring_soon_window_is_a_setting(): void
    {
        app(Settings::class)->set(['expiring_soon_days' => 3]);

        $this->assertSame(MembershipStatus::Active, $this->statusOf($this->memberExpiringIn(4)));
        $this->assertSame(MembershipStatus::Expiring, $this->statusOf($this->memberExpiringIn(3)));
    }

    public function test_deactivated_member_is_inactive_even_with_a_valid_plan(): void
    {
        $member = $this->memberExpiringIn(20, [], ['is_active' => false]);

        $this->assertSame(MembershipStatus::Inactive, $this->statusOf($member));
    }

    public function test_member_without_any_plan_has_no_plan_status(): void
    {
        $this->assertSame(MembershipStatus::None, $this->statusOf(Member::factory()->create()));
    }

    public function test_per_session_bundle_with_no_sessions_left_is_expired(): void
    {
        $member = $this->memberExpiringIn(30, ['sessions_total' => 10, 'sessions_remaining' => 0]);
        $this->assertSame(MembershipStatus::Expired, $this->statusOf($member));

        $member = $this->memberExpiringIn(30, ['sessions_total' => 10, 'sessions_remaining' => 1]);
        $this->assertSame(MembershipStatus::Active, $this->statusOf($member));
    }

    public function test_status_comes_from_the_latest_membership(): void
    {
        $member = $this->memberExpiringIn(-10);
        $member->memberships()->create(['plan_id' => $this->plan->id, 'start_date' => today(), 'expiry_date' => today()->addDays(30)]);

        $this->assertSame(MembershipStatus::Active, $this->statusOf($member->fresh()));
    }

    public function test_database_filters_agree_with_the_per_member_status(): void
    {
        app(Settings::class)->set(['grace_days' => 2]);

        $members = collect([
            $this->memberExpiringIn(30),
            $this->memberExpiringIn(8),
            $this->memberExpiringIn(7),
            $this->memberExpiringIn(0),
            $this->memberExpiringIn(-2),
            $this->memberExpiringIn(-3),
            $this->memberExpiringIn(20, ['sessions_total' => 5, 'sessions_remaining' => 0]),
            $this->memberExpiringIn(20, [], ['is_active' => false]),
            Member::factory()->create(),
        ]);

        // A renewed member: old expired period + new active period.
        $renewed = $this->memberExpiringIn(-15);
        $renewed->memberships()->create(['plan_id' => $this->plan->id, 'start_date' => today(), 'expiry_date' => today()->addDays(30)]);
        $members->push($renewed->fresh());

        foreach (MembershipStatus::cases() as $status) {
            $expected = $members->filter(fn ($m) => $this->statusOf($m) === $status)->pluck('id')->sort()->values()->all();
            $actual = $this->service()->scopeToStatus(Member::query(), $status)->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, "Mismatch for status {$status->value}");
        }

        $this->assertSame(
            ['active' => 3, 'expiring' => 3, 'expired' => 2, 'inactive' => 1, 'none' => 1],
            $this->service()->statusCounts(),
        );
    }
}
