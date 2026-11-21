<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Support\Backups\BackupService;
use App\Support\System\SystemInfo;
use App\Support\System\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** REQ §23.5: create, download, delete and (Super Admin only, guarded) restore backups. */
class BackupController extends Controller
{
    public const RESTORE_PHRASE = 'RESTORE';

    public function index(Request $request, SystemSettings $settings): Response
    {
        Gate::authorize('backups.manage');

        return Inertia::render('admin/system/Backups', [
            'backups' => Backup::query()->with('creator:id,name')->latest('id')->limit(100)->get()->map(fn (Backup $b) => [
                'id' => $b->id, 'type' => $b->type, 'trigger' => $b->trigger, 'filename' => $b->filename, 'size' => SystemInfo::format($b->size),
                'status' => $b->status, 'error' => $b->error, 'by' => $b->creator?->name, 'when' => $b->created_at?->toDayDateTimeString(),
                'restored_at' => $b->restored_at?->toDayDateTimeString(),
            ]),
            'canRestore' => $request->user()->hasRole(Role::SuperAdmin->value),
            'phrase' => self::RESTORE_PHRASE,
            'groups' => ['backups' => collect(SystemSettings::groups()['backups'])->except('permission')->all()],
            ...$settings->forAdmin(['backups']),
            'running' => Backup::query()->where('status', 'running')->exists(),
        ]);
    }

    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        Gate::authorize('backups.manage');

        $type = $request->validate(['type' => ['required', 'in:database,files']])['type'];
        $user = $request->user();

        dispatch(fn () => $backups->create($type, 'manual', $user->id))->afterResponse();
        activity('system')->causedBy($user)->withProperties(['type' => $type])->log('Backup started');
        $this->toast(['type' => 'success', 'message' => __('Backup started. It will appear in the list in a moment.')]);

        return back();
    }

    public function download(Request $request, Backup $backup): BinaryFileResponse
    {
        Gate::authorize('backups.manage');
        abort_unless($backup->status === 'done' && is_file(BackupService::path($backup)), 404);

        activity('security')->causedBy($request->user())->withProperties(['file' => $backup->filename])->log('Backup downloaded');

        return response()->download(BackupService::path($backup), $backup->filename);
    }

    public function destroy(Request $request, Backup $backup, BackupService $backups): RedirectResponse
    {
        Gate::authorize('backups.manage');

        $backups->delete($backup);
        activity('system')->causedBy($request->user())->withProperties(['file' => $backup->filename])->log('Backup deleted');
        $this->toast(['type' => 'success', 'message' => __('Backup deleted.')]);

        return back();
    }

    /**
     * Guarded restore: Super Admin only, their password, the typed phrase, and a
     * safety backup of the current data first. Everything in the backup replaces
     * what is in the database now.
     */
    public function restore(Request $request, Backup $backup, BackupService $backups): RedirectResponse
    {
        Gate::authorize('backups.manage');
        abort_unless($request->user()->hasRole(Role::SuperAdmin->value), 403);
        abort_unless($backup->type === 'database' && $backup->status === 'done' && is_file(BackupService::path($backup)), 404);

        $data = $request->validate(['password' => ['required', 'string'], 'phrase' => ['required', 'string']]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('That password is not correct.')]);
        }

        if ($data['phrase'] !== self::RESTORE_PHRASE) {
            throw ValidationException::withMessages(['phrase' => __('Type :phrase to confirm.', ['phrase' => self::RESTORE_PHRASE])]);
        }

        $safety = $backups->create('database', 'safety', $request->user()->id);

        if ($safety->status !== 'done') {
            throw ValidationException::withMessages(['password' => __('The safety backup failed, so nothing was restored.')]);
        }

        activity('security')->causedBy($request->user())->withProperties(['file' => $backup->filename, 'safety' => $safety->filename])->log('Database restore started');

        try {
            $backups->restore($backup);
        } catch (Throwable $e) {
            Log::error('Backup restore failed: '.$e->getMessage());
            $this->toast(['type' => 'error', 'message' => __('The restore failed part-way. Restore the safety backup :file to return to where you were.', ['file' => $safety->filename])]);

            return back();
        }

        Backup::query()->whereKey($backup->id)->update(['restored_at' => now()]);
        $this->toast(['type' => 'success', 'message' => __('Backup restored. A safety backup of the previous data was kept.')]);

        return back();
    }
}
