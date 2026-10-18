<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Batch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One-time initialization for approved demo History in NativePHP's active SQLite DB.
 *
 * Important: called ONLY by NativeAppServiceProvider during desktop startup, not
 * by Laravel's general AppServiceProvider or any HTTP request/migration.
 */
final class InitializeDesktopDemoHistory
{
    private const STATE_KEY = 'bundled-demo-history-v1';

    public function run(): void
    {
        try {
            // Avoid accidentally seeding XAMPP MySQL or another DB connection.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Log::warning('Certify desktop demo initialization skipped: active DB is not SQLite.');
                return;
            }

            if (! Schema::hasTable('certify_desktop_bootstrap_states') ||
                ! Schema::hasTable('batches') ||
                ! Schema::hasColumn('batches', 'bundled_demo_key')) {
                Log::warning('Certify desktop demo initialization postponed: database migrations are missing. Run php artisan native:migrate in development.');
                return;
            }

            $states = DB::table('certify_desktop_bootstrap_states');
            if ($states->where('key', self::STATE_KEY)->exists()) {
                return;
            }

            $manifest = resource_path('certify/bundled/history/manifest.json');
            if (! is_file($manifest)) {
                Log::warning('Certify desktop demo initialization postponed: bundled History manifest is missing.');
                return;
            }

            // We must never add demo History to a pre-existing user's library on an update.
            // This check includes soft-deleted records. Once skipped, never prompt again.
            if (Batch::withTrashed()->exists()) {
                $this->recordState('skipped-existing-history');
                Log::info('Certify desktop demo initialization skipped: user History already exists.');
                return;
            }

            $exitCode = Artisan::call('certify:install-demo-history');
            if ($exitCode !== 0) {
                Log::error('Certify desktop demo initialization failed; will retry next launch.', [
                    'output' => mb_substr(Artisan::output(), 0, 3000),
                ]);
                return;
            }

            // Confirm the command really imported bundled samples before marking complete.
            $raw = file_get_contents($manifest);
            $payload = $raw === false ? null : json_decode($raw, true);
            $entries = is_array($payload) ? ($payload['batches'] ?? null) : null;
            if (! is_array($entries) || count($entries) === 0) {
                Log::error('Certify desktop demo initialization did not find bundled History entries after importing.');
                return;
            }
            $keys = array_column($entries, 'key');
            if (count($keys) !== count($entries) ||
                Batch::withTrashed()->whereIn('bundled_demo_key', $keys)->count() !== count($entries)) {
                Log::error('Certify desktop demo initialization could not confirm all imported batches.');
                return;
            }

            $this->recordState('installed');
            Log::info('Certify desktop demo History initialized once.', [
                'batches' => count($entries),
            ]);
        } catch (Throwable $exception) {
            // Do not prevent the main desktop window from opening; do not mark complete.
            Log::error('Certify desktop demo initialization deferred following an error.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function recordState(string $result): void
    {
        DB::table('certify_desktop_bootstrap_states')->insertOrIgnore([
            'key' => self::STATE_KEY,
            'result' => $result,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
