<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        Gate::authorize('manage-backups');

        return view('backups.index', [
            'backups' => $this->backups->list(),
            'directory' => $this->backups->directory(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-backups');

        try {
            $backup = $this->backups->run();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        activity('backups')->causedBy($request->user())->event('created')->withProperties(['file' => $backup['file']])->log('Manual backup');

        return back()->with('success', "Backup saved: {$backup['file']}");
    }

    public function download(string $file): BinaryFileResponse
    {
        Gate::authorize('manage-backups');

        try {
            return response()->download($this->backups->path($file));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    public function restore(RestoreBackupRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $result = $this->backups->restore($request->validated('file'), $user);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Restore failed: '.$e->getMessage().' Your data was not changed unless the message says otherwise; a safety backup is in the list.');
        }

        // The restored database has its own sessions, so everyone signs in again.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', "Restore complete. Safety backup of the previous data: {$result['safety_backup']}. Please log in again.");
    }

    public function destroy(string $file): RedirectResponse
    {
        Gate::authorize('manage-backups');
        Gate::authorize('delete-records');

        try {
            $this->backups->delete($file);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        activity('backups')->causedBy(Auth::user())->event('deleted')->withProperties(['file' => $file])->log('Backup deleted');

        return back()->with('success', "Backup {$file} deleted.");
    }
}
