<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Walkin;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WalkinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-14 18:45:00');
    }

    public function test_walk_in_flow_uses_day_pass_price_and_server_time(): void
    {
        $dayPass = Plan::factory()->dayPass(80)->create(['name' => 'Day Pass']);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('walkins.index'))->assertOk()->assertSee('₱80.00');

        $this->post(route('walkins.store'), ['name' => 'Carlo Reyes', 'method' => 'cash', 'amount' => '1'])
            ->assertRedirect(route('walkins.index'));

        $walkin = Walkin::firstOrFail();
        $this->assertSame('80.00', $walkin->amount, 'amount is fixed when the price is not editable');
        $this->assertSame('2026-11-14 18:45:00', $walkin->visited_at->format('Y-m-d H:i:s'));
        $this->assertTrue($walkin->plan->is($dayPass));
        $this->assertTrue($walkin->recorder->is($staff));

        $this->get(route('walkins.index'))->assertSee('Carlo Reyes')->assertSee('6:45 PM');
    }

    public function test_guest_name_is_optional(): void
    {
        Plan::factory()->dayPass()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('walkins.store'), ['method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Walk-in guest', Walkin::first()->displayName());
    }

    public function test_amount_can_be_changed_only_when_the_setting_allows_it(): void
    {
        Plan::factory()->dayPass(80)->create();
        app(Settings::class)->set(['walkin_price_editable' => true]);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('walkins.store'), ['method' => 'cash', 'amount' => '50'])
            ->assertSessionHasNoErrors();

        $this->assertSame('50.00', Walkin::first()->amount);
    }

    public function test_walk_in_is_refused_without_a_day_pass_plan(): void
    {
        Plan::factory()->dayPass()->inactive()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('walkins.store'), ['method' => 'cash'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Walkin::count());
    }

    public function test_e_wallet_walk_in_needs_reference(): void
    {
        Plan::factory()->dayPass()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('walkins.store'), ['method' => 'gcash'])
            ->assertSessionHasErrors('reference_no');
    }

    public function test_only_admin_can_delete_walk_ins(): void
    {
        Plan::factory()->dayPass()->create();
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('walkins.store'), ['method' => 'cash']);
        $walkin = Walkin::firstOrFail();

        $this->delete(route('walkins.destroy', $walkin))->assertForbidden();
        $this->assertModelExists($walkin);

        $this->actingAs(User::factory()->admin()->create())->delete(route('walkins.destroy', $walkin))->assertRedirect();
        $this->assertModelMissing($walkin);
    }

    public function test_sold_day_pass_plan_cannot_be_deleted(): void
    {
        $dayPass = Plan::factory()->dayPass()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('walkins.store'), ['method' => 'cash']);

        $this->delete(route('plans.destroy', $dayPass))->assertSessionHas('error');
        $this->assertModelExists($dayPass);
    }
}
