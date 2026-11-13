<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Walkin;
use App\Services\MembershipService;
use Database\Seeders\EssentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_creates_a_realistic_mix(): void
    {
        Carbon::setTestNow('2026-11-14 15:00:00');

        $this->seed();

        $this->assertSame(2, User::count());
        $this->assertSame(5, Plan::count());
        $this->assertSame(40, Member::count());
        $this->assertSame(
            ['active' => 23, 'expiring' => 6, 'expired' => 7, 'inactive' => 2, 'none' => 2],
            app(MembershipService::class)->statusCounts(),
        );
        $this->assertGreaterThan(40, Payment::count());
        $this->assertTrue(Payment::where('kind', 'renewal')->exists());
        $this->assertGreaterThan(500, Attendance::count());
        $this->assertTrue(Attendance::where('status', 'denied')->exists() || Attendance::where('status', 'manual')->exists());
        $this->assertGreaterThan(50, Walkin::count());
        $this->assertSame(0, Attendance::where('checked_in_at', '>', now())->count(), 'no visits in the future');
        $this->assertSame('Barangay Fitness Gym', setting('gym_name'));
    }

    public function test_essentials_seeder_has_no_demo_data(): void
    {
        $this->seed(EssentialsSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(5, Plan::count());
        $this->assertSame(0, Member::count());
    }
}
