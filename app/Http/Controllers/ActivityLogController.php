<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/** Read-only view of the activity log (who did what, when). Admin only. */
class ActivityLogController extends Controller
{
    public const LOGS = [
        'payments' => 'Payments', 'walkins' => 'Walk-ins', 'attendance' => 'Attendance', 'members' => 'Members',
        'plans' => 'Plans', 'accounts' => 'Staff accounts', 'settings' => 'Settings', 'backups' => 'Backups',
    ];

    public function __invoke(Request $request): View
    {
        Gate::authorize('view-activity-log');

        $filters = $request->validate([
            'log' => ['nullable', 'in:'.implode(',', array_keys(self::LOGS))],
            'user' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $activities = Activity::with(['causer', 'subject'])
            ->when($filters['log'] ?? null, fn ($q, $log) => $q->where('log_name', $log))
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', $date))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('activity-log.index', [
            'activities' => $activities,
            'logs' => self::LOGS,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
