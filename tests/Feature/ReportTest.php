<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use App\Services\ReportService;
use App\Services\WalkinService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
        $monthly = Plan::factory()->monthly()->create(['name' => 'Monthly']);
        Plan::factory()->dayPass(80)->create(['name' => 'Day Pass']);

        // Nov 10: one new member pays 800 by cash, one walk-in (80)
        Carbon::setTestNow('2026-11-10 08:00:00');
        $ana = Member::factory()->create(['first_name' => 'Ana', 'last_name' => 'Reyes', 'joined_on' => '2026-11-10']);
        app(MembershipService::class)->recordPayment($ana, $monthly, ['amount' => 800, 'method' => 'cash'], $this->staff);
        app(AttendanceService::class)->checkIn($ana->fresh(), $this->staff);
        app(WalkinService::class)->record(['method' => 'cash'], $this->staff);

        // Nov 12: an older member renews by GCash (750, discounted)
        Carbon::setTestNow('2026-11-12 18:30:00');
        $ben = Member::factory()->create(['first_name' => 'Ben', 'last_name' => 'Santos', 'joined_on' => '2026-08-01']);
        $ben->memberships()->create(['plan_id' => $monthly->id, 'start_date' => '2026-10-12', 'expiry_date' => '2026-11-11']);
        app(MembershipService::class)->recordPayment($ben->fresh(), $monthly, ['amount' => 750, 'method' => 'gcash', 'reference_no' => 'GC123'], $this->staff);
        app(AttendanceService::class)->checkIn($ben->fresh(), $this->staff);

        Carbon::setTestNow('2026-11-14 12:00:00');
    }

    private function range(): array
    {
        return [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-14')];
    }

    public function test_income_report_keeps_walk_ins_separate(): void
    {
        $income = app(ReportService::class)->income(...$this->range());

        $this->assertEquals(['members' => 1550.0, 'walkins' => 80.0, 'total' => 1630.0, 'payments_count' => 2, 'walkins_count' => 1], $income['totals']);
        $nov10 = $income['daily']->firstWhere('date', '2026-11-10');
        $this->assertEquals([800.0, 80.0, 880.0], [$nov10['members'], $nov10['walkins'], $nov10['total']]);
        $this->assertCount(14, $income['daily']);
        $this->assertEquals(['Monthly', 'Walk-ins (day pass)'], $income['byPlan']->pluck('label')->all());
        $this->assertEquals(880.0, $income['byMethod']->firstWhere('label', 'Cash')['amount']);
        $this->assertEquals(750.0, $income['byMethod']->firstWhere('label', 'GCash')['amount']);
    }

    public function test_attendance_report_counts_members_and_walk_ins(): void
    {
        $att = app(ReportService::class)->attendance(...$this->range());

        $this->assertSame(2, $att['totals']['members']);
        $this->assertSame(1, $att['totals']['walkins']);
        $this->assertSame(3, $att['totals']['total']);
        $this->assertSame(2, $att['daily']->firstWhere('date', '2026-11-10')['total']);
        $this->assertSame(2, $att['byHour'][8]);
    }

    public function test_members_report_splits_new_members_first_plans_and_renewals(): void
    {
        $report = app(ReportService::class)->members(...$this->range());

        $this->assertSame(1, $report['totals']['new_members']);
        $this->assertSame(1, $report['totals']['first_payments']);
        $this->assertSame(1, $report['totals']['renewals']);
        $this->assertEquals(750.0, $report['totals']['renewal_income']);
    }

    public function test_report_pages_render_for_each_type(): void
    {
        $this->actingAs($this->staff);

        $this->get(route('reports.index'))->assertOk()->assertSee('₱1,630.00');
        $this->get(route('reports.index', ['type' => 'attendance', 'preset' => 'this_month']))->assertOk()->assertSee('Busiest hours');
        $this->get(route('reports.index', ['type' => 'members', 'from' => '2026-11-01', 'to' => '2026-11-14']))->assertOk()->assertSee('Ana');
        $this->get(route('reports.index', ['from' => '2025-01-01', 'to' => '2026-11-14']))->assertSessionHasErrors('to');
    }

    public function test_excel_and_pdf_exports_download(): void
    {
        $this->actingAs($this->staff);

        foreach (['income', 'attendance', 'members'] as $type) {
            $xlsx = $this->get(route('reports.export', ['format' => 'xlsx', 'type' => $type, 'from' => '2026-11-01', 'to' => '2026-11-14']));
            $xlsx->assertOk();
            $this->assertStringContainsString("{$type}-report_2026-11-01_to_2026-11-14.xlsx", $xlsx->headers->get('content-disposition'));
            $this->assertStringStartsWith('PK', file_get_contents($xlsx->baseResponse->getFile()->getPathname()), 'xlsx is a zip file');

            $pdf = $this->get(route('reports.export', ['format' => 'pdf', 'type' => $type, 'from' => '2026-11-01', 'to' => '2026-11-14']));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }

        $this->get(route('reports.export', ['format' => 'csv']))->assertNotFound();
    }

    public function test_dashboard_shows_today_and_month(): void
    {
        Carbon::setTestNow('2026-11-12 20:00:00');

        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk()
            ->assertSee('₱750.00')        // income today
            ->assertSee('₱1,630.00')      // this month
            ->assertSee('Recent payments')
            ->assertSee('data-chart', false);
    }
}
