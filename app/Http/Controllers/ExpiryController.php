<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Expiring-soon and expired members, each with a Renew shortcut. */
class ExpiryController extends Controller
{
    public function __invoke(Request $request, MembershipService $memberships): View
    {
        Gate::authorize('viewAny', Member::class);

        $tab = $request->query('tab') === 'expired' ? 'expired' : 'expiring';
        $status = $tab === 'expired' ? MembershipStatus::Expired : MembershipStatus::Expiring;

        $members = $memberships->scopeToStatus(Member::query(), $status)
            ->with('currentMembership.plan')
            ->get()
            ->sortBy(fn (Member $m) => $m->currentMembership->expiry_date->timestamp * ($tab === 'expired' ? -1 : 1))
            ->values();

        return view('expiry.index', [
            'tab' => $tab,
            'members' => $members,
            'counts' => [
                'expiring' => $memberships->scopeToStatus(Member::query(), MembershipStatus::Expiring)->count(),
                'expired' => $memberships->scopeToStatus(Member::query(), MembershipStatus::Expired)->count(),
            ],
            'soonDays' => $memberships->expiringSoonDays(),
            'graceDays' => $memberships->graceDays(),
        ]);
    }
}
