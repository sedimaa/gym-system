<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    private function validPlan(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly Membership',
            'type' => 'monthly',
            'price' => '800.00',
            'duration_days' => 30,
            'description' => 'Unlimited gym access',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_admin_can_create_a_plan_and_it_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('plans.store'), $this->validPlan())
            ->assertRedirect(route('plans.index'));

        $plan = Plan::firstOrFail();
        $this->assertSame(PlanType::Monthly, $plan->type);
        $this->assertSame('800.00', $plan->price);
        $this->assertSame(30, $plan->duration_days);
        $this->assertNull($plan->session_count);

        $this->assertTrue(Activity::where('log_name', 'plans')->where('event', 'created')->exists());
    }

    public function test_per_session_plan_requires_a_session_count(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('plans.store'), $this->validPlan(['name' => '10 Sessions', 'type' => 'per_session']))
            ->assertSessionHasErrors('session_count');

        $this->actingAs($admin)->post(route('plans.store'), $this->validPlan(['name' => '10 Sessions', 'type' => 'per_session', 'session_count' => 10, 'duration_days' => 60]))
            ->assertSessionHasNoErrors();

        $this->assertSame(10, Plan::where('name', '10 Sessions')->value('session_count'));
    }

    public function test_day_pass_is_always_one_day_and_session_count_is_cleared(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('plans.store'), $this->validPlan([
            'name' => 'Day Pass', 'type' => 'day_pass', 'price' => 80, 'duration_days' => 30, 'session_count' => 5,
        ]))->assertSessionHasNoErrors();

        $plan = Plan::where('name', 'Day Pass')->firstOrFail();
        $this->assertSame(1, $plan->duration_days);
        $this->assertNull($plan->session_count);
    }

    public function test_price_change_is_logged_with_old_and_new_values(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->monthly()->create(['name' => 'Monthly']);

        $this->actingAs($admin)->put(route('plans.update', $plan), $this->validPlan(['name' => 'Monthly', 'price' => '900']))
            ->assertRedirect(route('plans.index'));

        $log = Activity::where('log_name', 'plans')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertEquals('800.00', $log->properties['old']['price']);
        $this->assertEquals('900.00', $log->properties['attributes']['price']);
        $this->assertTrue($log->causer->is($admin));
    }

    public function test_plan_names_must_be_unique(): void
    {
        Plan::factory()->create(['name' => 'Monthly Membership']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('plans.store'), $this->validPlan())
            ->assertSessionHasErrors('name');
    }

    public function test_unused_plan_can_be_deleted(): void
    {
        $plan = Plan::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index'));

        $this->assertModelMissing($plan);
    }

    public function test_admin_sees_plan_list_and_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->perSession(12)->create(['name' => 'Twelve Pack']);

        $this->actingAs($admin)->get(route('plans.index'))->assertOk()->assertSee('Twelve Pack')->assertSee('₱600.00');
        $this->get(route('plans.create'))->assertOk();
        $this->get(route('plans.edit', $plan))->assertOk()->assertSee('Twelve Pack');
    }

    public function test_staff_cannot_manage_plans(): void
    {
        $staff = User::factory()->staff()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($staff);
        $this->get(route('plans.index'))->assertForbidden();
        $this->get(route('plans.create'))->assertForbidden();
        $this->post(route('plans.store'), $this->validPlan(['name' => 'Cheap']))->assertForbidden();
        $this->get(route('plans.edit', $plan))->assertForbidden();
        $this->put(route('plans.update', $plan), $this->validPlan(['price' => 1]))->assertForbidden();
        $this->delete(route('plans.destroy', $plan))->assertForbidden();

        $this->assertDatabaseMissing('plans', ['name' => 'Cheap']);
        $this->assertModelExists($plan);
    }
}
