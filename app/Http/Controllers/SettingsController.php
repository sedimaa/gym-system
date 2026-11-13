<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Support\Branding;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function edit(): View
    {
        Gate::authorize('manage-settings');

        return view('settings.edit', [
            'values' => $this->settings->all(),
            'renewalModes' => config('gym.renewal_start_modes'),
            'hasLogo' => Branding::logoPath() !== null,
            'defaultBackupPath' => storage_path('app/backups'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = collect($request->validated())->except(['logo', 'remove_logo'])->all();
        $before = $this->settings->all();

        if ($request->boolean('remove_logo') && $before['gym_logo']) {
            File::delete(Branding::directory().DIRECTORY_SEPARATOR.basename($before['gym_logo']));
            $data['gym_logo'] = null;
        }

        if ($request->hasFile('logo')) {
            File::ensureDirectoryExists(Branding::directory());
            if ($before['gym_logo']) {
                File::delete(Branding::directory().DIRECTORY_SEPARATOR.basename($before['gym_logo']));
            }
            $name = 'logo-'.now()->format('YmdHis').'.'.$request->file('logo')->extension();
            $request->file('logo')->move(Branding::directory(), $name);
            $data['gym_logo'] = $name;
        }

        $this->settings->set($data);

        $changes = collect($data)->filter(fn ($v, $k) => ($before[$k] ?? null) != $v);
        if ($changes->isNotEmpty()) {
            activity('settings')->causedBy($request->user())->event('updated')
                ->withProperties(['old' => collect($before)->only($changes->keys())->all(), 'attributes' => $changes->all()])
                ->log('Settings changed: '.$changes->keys()->implode(', '));
        }

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }

    /** Logo for the sidebar and login page (public: it's just the gym's logo). */
    public function logo(): BinaryFileResponse
    {
        $path = Branding::logoPath();
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=3600']);
    }
}
