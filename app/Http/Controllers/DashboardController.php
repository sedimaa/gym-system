<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Payment;
use App\Services\MembershipService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports, MembershipService $memberships): View
    {
        $today = CarbonImmutable::today();

        $todayIncome = $reports->income($today, $today)['totals'];
        $monthIncome = $reports->income($today->startOfMonth(), $today)['totals'];
        $todayVisits = $reports->attendance($today, $today)['totals'];

        return view('dashboard', [
            'todayIncome' => $todayIncome,
            'monthIncome' => $monthIncome,
            'todayVisits' => $todayVisits,
            'statusCounts' => $memberships->statusCounts(),
            'incomeChart' => $reports->income($today->subDays(29), $today)['daily'],
            'attendanceChart' => $reports->attendance($today->subDays(13), $today)['daily'],
            'expiring' => $memberships->scopeToStatus(Member::query(), MembershipStatus::Expiring)
                ->with('currentMembership.plan')->get()
                ->sortBy(fn (Member $m) => $m->currentMembership->expiry_date)->take(8)->values(),
            'recentPayments' => Payment::with(['member', 'plan'])->latest('id')->limit(6)->get(),
        ]);
    }
}
