<?php

namespace App\Http\Controllers;

use App\Services\CertifyBackupService;
use Illuminate\Http\Request;
use RuntimeException;

/** Backups and recovery are intentionally available only on the local installation. */
class BackupController
{
    private function localOnly(Request $request): void
    {
        abort_unless(app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
    }

    public function index(Request $request, CertifyBackupService $service)
    {
        $this->localOnly($request);
        return view('settings.backup', [
            'driver' => $service->driver(),
            'backups' => $service->backups(),
        ]);
    }

    public function create(Request $request, CertifyBackupService $service)
    {
        $this->localOnly($request);
        $lockFile = fopen(storage_path('app/certify-restore.lock'), 'c+');
        if (! $lockFile || ! flock($lockFile, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockFile)) fclose($lockFile);
            return back()->withErrors(['backup' => 'Certificate generation or restoration is currently busy. Retry when it finishes.']);
        }
        try {
            $name = $service->newName();
            $path = $service->pathFor($name);
            $service->create($path);
            $result = $service->verify($path);
            return redirect()->route('settings.backup')->with('success', 'Backup created and verified: '.$name.' ('.$result['files'].' files).');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['backup' => 'Backup failed: '.$this->safeMessage($e)]);
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    /** Non-destructive backup client and MySQL login diagnostics. */
    public function diagnose(Request $request, CertifyBackupService $service)
    {
        $this->localOnly($request);
        try {
            return back()->with('success', $service->mysqlDiagnostics());
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['backup' => 'MySQL check failed: '.$this->safeMessage($e)]);
        }
    }

    public function download(Request $request, CertifyBackupService $service, string $name)
    {
        $this->localOnly($request);
        try {
            $path = $service->pathFor($name);
            abort_unless(is_file($path), 404);
            return response()->download($path, $name, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (RuntimeException $e) { abort(404); }
    }

    public function verify(Request $request, CertifyBackupService $service, string $name)
    {
        $this->localOnly($request);
        try {
            $path = $service->pathFor($name);
            if (! is_file($path)) abort(404);
            $result = $service->verify($path);
            return back()->with('success', 'Verified '.$name.': '.$result['files'].' files, '.$result['driver'].' database, created '.$result['created_at'].'.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['backup' => 'Verification failed: '.$this->safeMessage($e)]);
        }
    }

    /** Delete only an explicitly confirmed saved ZIP, never the live data. */
    public function destroy(Request $request, CertifyBackupService $service, string $name)
    {
        $this->localOnly($request);
        $request->validate([
            'confirmation' => ['required', 'in:DELETE BACKUP'],
        ], ['confirmation.in' => 'Type DELETE BACKUP exactly to confirm permanent deletion.']);

        $lockFile = fopen(storage_path('app/certify-restore.lock'), 'c+');
        if (! $lockFile || ! flock($lockFile, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockFile)) fclose($lockFile);
            return back()->withErrors(['backup' => 'A backup or restore operation is in progress. Try again when it finishes.']);
        }

        try {
            if (is_file(storage_path('app/certify-restore-in-progress'))) {
                throw new RuntimeException('Cannot delete backups while restore maintenance is active.');
            }
            // Re-evaluate newest status inside the lock: never trust a stale page/hidden form field.
            $newest = $service->backups(1);
            $isNewest = ($newest[0]['name'] ?? null) === $name;
            if ($isNewest) {
                $request->validate([
                    'acknowledge_latest' => ['accepted'],
                    'latest_confirmation' => ['required', 'in:DELETE LATEST BACKUP'],
                ], [
                    'acknowledge_latest.accepted' => 'Confirm that you understand this is the newest backup.',
                    'latest_confirmation.in' => 'Type DELETE LATEST BACKUP exactly for the newest archive.',
                ]);
            }
            $service->delete($name, $isNewest && $request->boolean('acknowledge_latest'));
            return redirect()->route('settings.backup')->with('success', 'Backup permanently deleted: '.$name.'.');
        } catch (RuntimeException $e) {
            report($e);
            return back()->withErrors(['backup' => 'Deletion stopped: '.$this->safeMessage($e)]);
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    public function upload(Request $request, CertifyBackupService $service)
    {
        $this->localOnly($request);
        // Do not impose an application upload-size cap. PHP/server limits and ZIP safety verification still apply.
        $request->validate(['archive' => ['required', 'file']]);
        $upload = $request->file('archive');
        if (! $upload || strtolower((string) $upload->getClientOriginalExtension()) !== 'zip') {
            return back()->withErrors(['archive' => 'Choose a Certify .zip backup.']);
        }
        $name = $service->newName('Imported_Certify');
        $path = $service->pathFor($name);
        try {
            if (! $upload->move(dirname($path), basename($path))) throw new RuntimeException('Could not store uploaded backup.');
            $result = $service->verify($path);
            return back()->with('success', 'Uploaded and verified '.$name.' ('.$result['driver'].', '.$result['files'].' files).');
        } catch (\Throwable $e) {
            @unlink($path);
            report($e);
            return back()->withErrors(['backup' => 'Backup upload rejected: '.$this->safeMessage($e)]);
        }
    }

    public function restore(Request $request, CertifyBackupService $service, string $name)
    {
        $this->localOnly($request);
        $request->validate([
            'confirmation' => ['required', 'in:RESTORE CERTIFY'],
            'acknowledge' => ['accepted'],
        ], ['confirmation.in' => 'Type RESTORE CERTIFY exactly to confirm replacement.']);

        $lockPath = storage_path('app/certify-restore.lock');
        $lockFile = fopen($lockPath, 'c+');
        if (! $lockFile || ! flock($lockFile, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockFile)) fclose($lockFile);
            return back()->withErrors(['backup' => 'Another restore is in progress.']);
        }
        $marker = storage_path('app/certify-restore-in-progress');
        $safetyName = null;
        $ownedMarker = false;
        try {
            $path = $service->pathFor($name);
            if (! is_file($path)) abort(404);
            $result = $service->verify($path);
            if ($result['driver'] !== $service->driver()) {
                throw new RuntimeException('Database mismatch: this backup uses '.$result['driver'].' but Certify uses '.$service->driver().'.');
            }
            if (is_file($marker)) throw new RuntimeException('An earlier restore needs attention. Restart only after reviewing storage/logs.');
            if (function_exists('set_time_limit')) @set_time_limit(900);
            $safetyName = $service->newName('Certify_BeforeRestore');
            $service->create($service->pathFor($safetyName));
            $service->verify($service->pathFor($safetyName));
            // Block new batch processing while local data is being replaced.
            if (file_put_contents($marker, 'Restoring '.date('c')) === false) {
                throw new RuntimeException('Could not enter restore maintenance mode.');
            }
            $ownedMarker = true;
            $service->restore($path);
            return redirect()->route('settings.backup')->with('success', 'Restore completed. A safety copy of the previous data was saved as '.$safetyName.'. Restart Certify before working on batches.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['backup' => 'Restore stopped: '.$this->safeMessage($e).($safetyName ? ' Pre-restore safety copy: '.$safetyName.'.' : '')]);
        } finally {
            if ($ownedMarker) @unlink($marker);
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    public function background(Request $request)
    {
        $this->localOnly($request);
        return view('settings.background');
    }

    private function safeMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        // Hide credentials and local filesystem details from the browser.
        return strlen($message) < 230 && ! str_contains($message, "\n")
            ? $message
            : 'Please check the application log for details.';
    }
}
