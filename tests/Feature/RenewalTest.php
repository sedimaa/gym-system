<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Services\MembershipService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private Plan $monthly;

    private Plan $quarterly;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->monthly = Plan::factory()->monthly()->create(['name' => 'Monthly']);
        $this->quarterly = Plan::factory()->quarterly()->create(['name' => 'Quarterly']);
        $this->staff = User::factory()->staff()->create();
    }

    private function pay(Member $member, Plan $plan, array $data = []): Membership
    {
        return app(MembershipService::class)
            ->recordPayment($member, $plan, array_merge(['amount' => $plan->price, 'method' => 'cash'], $data), $this->staff)
            ->membership;
    }

    /** Member with a monthly period that expires on $expiry. */
    private function memberWithMonthlyExpiring(string $expiry): Member
    {
        $member = Member::factory()->create();
        $member->memberships()->create([
            'plan_id' => $this->monthly->id,
            'start_date' => Carbon::parse($expiry)->subDays(30),
            'expiry_date' => $expiry,
        ]);

        return $member->fresh();
    }

    public function test_first_payment_starts_today_and_expiry_is_start_plus_duration(): void
    {
        $membership = $this->pay(Member::factory()->create(), $this->monthly);

        $this->assertSame('2026-11-14', $membership->start_date->toDateString());
        $this->assertSame('2026-12-14', $membership->expiry_date->toDateString());
        $this->assertSame('new', $membership->payment->kind);
    }

    public function test_renewal_from_payment_date_mode(): void
    {
        app(Settings::class)->set(['renewal_start_mode' => 'from_payment_date']);
        $member = $this->memberWithMonthlyExpiring('2026-11-20');

        $membership = $this->pay($member, $this->monthly);

        $this->assertSame('2026-11-14', $membership->start_date->toDateString());
        $this->assertSame('2026-12-14', $membership->expiry_date->toDateString());
        $this->assertSame('renewal', $membership->payment->kind);
    }

    public function test_renewal_from_old_expiry_mode_continues_after_the_current_period(): void
    {
        app(Settings::class)->set(['renewal_start_mode' => 'from_old_expiry']);
        $member = $this->memberWithMonthlyExpiring('2026-11-20');

        $membership = $this->pay($member, $this->monthly);

        $this->assertSame('2026-11-21', $membership->start_date->toDateString());
        $this->assertSame('2026-12-21', $membership->expiry_date->toDateString());
    }

    public function test_from_old_expiry_when_paying_on_the_expiry_day_still_continues(): void
    {
        app(Settings::class)->set(['renewal_start_mode' => 'from_old_expiry']);
        $member = $this->memberWithMonthlyExpiring('2026-11-14');

        $this->assertSame('2026-11-15', $this->pay($member, $this->monthly)->start_date->toDateString());
    }

    public function test_from_old_expiry_falls_back_to_payment_date_when_already_expired(): void
    {
        app(Settings::class)->set(['renewal_start_mode' => 'from_old_expiry']);
        $member = $this->memberWithMonthlyExpiring('2026-11-01');

        $membership = $this->pay($member, $this->monthly);

        $this->assertSame('2026-11-14', $membership->start_date->toDateString());
        $this->assertSame('2026-12-14', $membership->expiry_date->toDateString());
    }

    public function test_changing_plan_starts_on_payment_date_with_no_credit_in_either_mode(): void
    {
        foreach (['from_payment_date', 'from_old_expiry'] as $mode) {
            app(Settings::class)->set(['renewal_start_mode' => $mode]);
            $member = $this->memberWithMonthlyExpiring('2026-11-30');

            $membership = $this->pay($member, $this->quarterly);

            $this->assertSame('2026-11-14', $membership->start_date->toDateString(), $mode);
            $this->assertSame('2027-02-12', $membership->expiry_date->toDateString(), $mode);
        }
    }

    public function test_per_session_bundle_gets_its_sessions(): void
    {
        $bundle = Plan::factory()->perSession(10)->create(['name' => '10 Sessions']);

        $membership = $this->pay(Member::factory()->create(), $bundle);

        $this->assertSame(10, $membership->sessions_total);
        $this->assertSame(10, $membership->sessions_remaining);
        $this->assertSame('2027-01-13', $membership->expiry_date->toDateString());
    }

    public function test_used_up_bundle_renewal_starts_on_payment_date_even_in_old_expiry_mode(): void
    {
        app(Settings::class)->set(['renewal_start_mode' => 'from_old_expiry']);
        $bundle = Plan::factory()->perSession(10)->create(['name' => '10 Sessions']);
        $member = Member::factory()->create();
        $member->memberships()->create([
            'plan_id' => $bundle->id, 'start_date' => '2026-11-01', 'expiry_date' => '2026-12-31',
            'sessions_total' => 10, 'sessions_remaining' => 0,
        ]);

        $this->assertSame('2026-11-14', $this->pay($member->fresh(), $bundle)->start_date->toDateString());
    }

    public function test_payment_and_membership_are_saved_together_or_not_at_all(): void
    {
        $member = Member::factory()->create();

        try {
            app(MembershipService::class)->recordPayment($member, $this->monthly, ['amount' => null, 'method' => 'cash'], $this->staff);
            $this->fail('Expected the payment insert to fail.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, $member->memberships()->count(), 'membership must be rolled back');
        $this->assertSame(0, $member->payments()->count());
    }

    public function test_deactivated_members_cannot_pay_and_day_pass_is_not_a_member_plan(): void
    {
        $service = app(MembershipService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->recordPayment(Member::factory()->deactivated()->create(), $this->monthly, ['amount' => 800, 'method' => 'cash'], $this->staff);
    }

    public function test_day_pass_cannot_be_sold_as_membership(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(MembershipService::class)->recordPayment(Member::factory()->create(), Plan::factory()->dayPass()->create(), ['amount' => 80, 'method' => 'cash'], $this->staff);
    }
}
