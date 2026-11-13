<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private function validMember(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => 'Protacio',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'birthdate' => '1995-06-12',
            'phone' => '0917 123 4567',
            'address' => 'Purok 3, Brgy. San Isidro',
            'joined_on' => today()->toDateString(),
        ], $overrides);
    }

    public function test_staff_can_register_a_member_and_gets_a_sequential_code(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('members.store'), $this->validMember())->assertRedirect();
        $this->post(route('members.store'), $this->validMember(['first_name' => 'Maria', 'last_name' => 'Santos']))->assertRedirect();

        $this->assertSame(['GYM-0001', 'GYM-0002'], Member::orderBy('id')->pluck('member_code')->all());
        $this->assertSame('Juan P. Dela Cruz', Member::first()->full_name);
        $this->assertTrue(Activity::where('log_name', 'members')->where('event', 'created')->exists());
    }

    public function test_member_code_never_reuses_an_existing_code(): void
    {
        Member::factory()->create();             // GYM-0001
        $second = Member::factory()->create();   // GYM-0002
        $second->forceFill(['member_code' => 'GYM-0003'])->save();

        $this->assertSame('GYM-0004', Member::factory()->create()->member_code);
    }

    public function test_registration_validates_required_fields_and_phone(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('members.store'), $this->validMember(['first_name' => '', 'phone' => 'call me', 'birthdate' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['first_name', 'phone', 'birthdate']);

        $this->assertSame(0, Member::count());
    }

    public function test_search_matches_name_code_and_phone(): void
    {
        Member::factory()->create(['first_name' => 'Rosalinda', 'last_name' => 'Villanueva', 'phone' => '09181112222']);
        Member::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Penduko', 'phone' => '09993334444']);

        $this->actingAs(User::factory()->staff()->create());

        $this->get(route('members.index', ['q' => 'rosa villa']))->assertSee('Villanueva')->assertDontSee('Penduko');
        $this->get(route('members.index', ['q' => 'GYM-0002']))->assertSee('Penduko')->assertDontSee('Villanueva');
        $this->get(route('members.index', ['q' => '3334444']))->assertSee('Penduko')->assertDontSee('Villanueva');
    }

    public function test_staff_can_edit_member_details_and_the_edit_is_logged(): void
    {
        $staff = User::factory()->staff()->create();
        $member = Member::factory()->create(['phone' => '09170000000']);

        $this->actingAs($staff)->put(route('members.update', $member), $this->validMember(['phone' => '09179999999']))
            ->assertRedirect(route('members.show', $member));

        $this->assertSame('09179999999', $member->fresh()->phone);
        $log = Activity::where('log_name', 'members')->where('event', 'updated')->latest('id')->first();
        $this->assertSame('09170000000', $log->properties['old']['phone']);
        $this->assertTrue($log->causer->is($staff));
    }

    public function test_profile_and_forms_render(): void
    {
        $member = Member::factory()->create(['notes' => 'Bad knee']);

        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('members.show', $member))->assertOk()->assertSee($member->member_code)->assertSee('Bad knee');
        $this->get(route('members.create'))->assertOk();
        $this->get(route('members.edit', $member))->assertOk();
    }

    public function test_admin_can_deactivate_and_reactivate_a_member(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create(['last_name' => 'Zamboanguena']);

        $this->actingAs($admin)->patch(route('members.deactivate', $member), ['deactivation_reason' => 'Moved to Cebu'])
            ->assertRedirect(route('members.show', $member));

        $member->refresh();
        $this->assertFalse($member->is_active);
        $this->assertSame('Moved to Cebu', $member->deactivation_reason);
        $this->assertTrue(Activity::where('subject_id', $member->id)->where('log_name', 'members')->where('event', 'updated')->exists());

        $this->get(route('members.index'))->assertViewHas('members', fn ($list) => ! $list->contains($member));
        $this->get(route('members.index', ['status' => 'inactive']))->assertViewHas('members', fn ($list) => $list->contains($member));

        $this->patch(route('members.reactivate', $member));
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_staff_cannot_deactivate_reactivate_or_delete_members(): void
    {
        $staff = User::factory()->staff()->create();
        $member = Member::factory()->create();
        $inactive = Member::factory()->deactivated()->create();

        $this->actingAs($staff);
        $this->patch(route('members.deactivate', $member))->assertForbidden();
        $this->patch(route('members.reactivate', $inactive))->assertForbidden();
        $this->delete(route('members.destroy', $member))->assertForbidden();

        $this->assertTrue($member->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertModelExists($member);
    }

    public function test_admin_can_delete_a_member_without_history(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'));

        $this->assertModelMissing($member);
    }
}
