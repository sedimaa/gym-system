<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Plan $monthly;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 09:30:00');
        $this->monthly = Plan::factory()->monthly()->create(['name' => 'Monthly']);
    }

    private function postPayment(Member $member, array $overrides = [])
    {
        return $this->post(route('payments.store'), array_merge([
            'member_id' => $member->id,
            'plan_id' => $this->monthly->id,
            'amount' => '800',
            'method' => 'cash',
        ], $overrides));
    }

    public function test_staff_records_a_payment_which_creates_the_membership(): void
    {
        $staff = User::factory()->staff()->create();
        $member = Member::factory()->create();

        $response = $this->actingAs($staff)->postPayment($member);

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame('OR-000001', $payment->receipt_no);
        $this->assertTrue($payment->receiver->is($staff));
        $this->assertSame('2026-12-14', $member->fresh()->currentMembership->expiry_date->toDateString());

        $log = Activity::where('log_name', 'payments')->firstOrFail();
        $this->assertTrue($log->causer->is($staff));
        $this->assertEquals('800.00', $log->properties['attributes']['amount']);
    }

    public function test_staff_cannot_backdate_but_admin_can(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->staff()->create())->postPayment($member, ['paid_on' => '2026-10-01']);
        $this->assertSame('2026-11-14', Payment::latest('id')->first()->paid_on->toDateString());

        $other = Member::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->postPayment($other, ['paid_on' => '2026-10-01']);
        $payment = Payment::latest('id')->first();
        $this->assertSame('2026-10-01', $payment->paid_on->toDateString());
        $this->assertSame('2026-10-31', $payment->membership->expiry_date->toDateString());
    }

    public function test_gcash_needs_a_reference_number(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->postPayment(Member::factory()->create(), ['method' => 'gcash'])
            ->assertSessionHasErrors('reference_no');

        $this->assertSame(0, Payment::count());
    }

    public function test_payments_are_refused_for_deactivated_members_day_passes_and_plans_not_for_sale(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->postPayment(Member::factory()->deactivated()->create())->assertSessionHasErrors('member_id');
        $this->postPayment(Member::factory()->create(), ['plan_id' => Plan::factory()->dayPass()->create()->id])->assertSessionHasErrors('plan_id');
        $this->postPayment(Member::factory()->create(), ['plan_id' => Plan::factory()->inactive()->create()->id])->assertSessionHasErrors('plan_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_payment_pages_and_receipt_pdf_render(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $member = Member::factory()->create();
        $this->postPayment($member);
        $payment = Payment::firstOrFail();

        $this->get(route('payments.create'))->assertOk();
        $this->get(route('payments.create', ['member_q' => $member->last_name]))->assertOk()->assertSee($member->member_code);
        $this->get(route('payments.create', ['member' => $member->id]))->assertOk()->assertSee('Monthly');
        $this->get(route('payments.show', $payment))->assertOk()->assertSee('OR-000001');
        $this->get(route('payments.index'))->assertOk()->assertSee('OR-000001')->assertSee('₱800.00');
        $this->get(route('members.show', $member))->assertOk()->assertSee('OR-000001')->assertSee('Dec 14, 2026');

        $pdf = $this->get(route('payments.receipt', $payment));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_staff_cannot_delete_payments(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->postPayment(Member::factory()->create());

        $this->delete(route('payments.destroy', Payment::first()))->assertForbidden();
        $this->assertSame(1, Payment::count());
    }

    public function test_admin_can_delete_only_the_latest_payment_and_its_period(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();
        $this->actingAs($admin)->postPayment($member);
        $this->postPayment($member);
        [$first, $second] = Payment::orderBy('id')->get()->all();

        $this->delete(route('payments.destroy', $first))->assertSessionHas('error');
        $this->assertModelExists($first);

        $this->delete(route('payments.destroy', $second))->assertRedirect(route('members.show', $member));
        $this->assertModelMissing($second);
        $this->assertSame(1, $member->memberships()->count());
        $this->assertTrue(Activity::where('log_name', 'payments')->where('event', 'deleted')->exists());
    }

    public function test_members_with_payments_cannot_be_deleted(): void
    {
        $member = Member::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->postPayment($member);

        $this->delete(route('members.destroy', $member))->assertSessionHas('error');
        $this->assertModelExists($member);
    }

    public function test_expiry_page_lists_members_with_renew_links(): void
    {
        $expiring = Member::factory()->create(['last_name' => 'Soonexpire']);
        $expiring->memberships()->create(['plan_id' => $this->monthly->id, 'start_date' => '2026-10-17', 'expiry_date' => '2026-11-16']);
        $expired = Member::factory()->create(['last_name' => 'Longgone']);
        $expired->memberships()->create(['plan_id' => $this->monthly->id, 'start_date' => '2026-09-01', 'expiry_date' => '2026-10-01']);

        $this->actingAs(User::factory()->staff()->create());

        $this->get(route('expiry.index'))->assertOk()->assertSee('Soonexpire')->assertDontSee('Longgone')->assertSee('2 days left')
            ->assertSee(route('payments.create', ['member' => $expiring->id, 'plan' => $this->monthly->id]));
        $this->get(route('expiry.index', ['tab' => 'expired']))->assertOk()->assertSee('Longgone')->assertDontSee('Soonexpire');
        $this->get(route('members.index', ['status' => 'expired']))->assertOk()->assertSee('Longgone')->assertDontSee('Soonexpire');
    }
}
