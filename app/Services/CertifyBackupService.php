<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/** Local-only verified backup archives for SQLite/MySQL and Certify private files. */
class CertifyBackupService
{
    public function driver(): string
    {
        $driver = (string) config('database.default');
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Only SQLite and MySQL backups are supported.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive is required. Enable the PHP zip extension.');
        }

        return $driver;
    }

    public function folder(): string
    {
        $path = storage_path('app/backups');
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create the backup directory.');
        }

        return $path;
    }

    /** An application-created filename; the user never controls the filesystem path. */
    public function newName(string $prefix = 'Certify'): string
    {
        return $prefix.'_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.zip';
    }

    public function pathFor(string $name): string
    {
        if (! preg_match('/\A(?:Certify|Imported_Certify|Certify_BeforeRestore)_[A-Za-z0-9_\-]+\.zip\z/D', $name)) {
            throw new RuntimeException('Invalid backup filename.');
        }
        return $this->folder().DIRECTORY_SEPARATOR.$name;
    }

    public function backups(int $limit = 30): array
    {
        $items = [];
        foreach (new \DirectoryIterator($this->folder()) as $item) {
            if ($item->isFile() && ! $item->isLink() && preg_match('/\A(?:Certify|Imported_Certify|Certify_BeforeRestore)_[A-Za-z0-9_\-]+\.zip\z/D', $item->getFilename())) {
                $items[] = ['name' => $item->getFilename(), 'bytes' => $item->getSize(), 'modified' => $item->getMTime()];
            }
        }
        usort($items, fn ($a, $b) => ($b['modified'] <=> $a['modified']) ?: strcmp($b['name'], $a['name']));
        return array_slice($items, 0, $limit);
    }

    /**
     * Permanently remove a single named archive. Never touch database/private working files.
     * Caller must hold the shared backup/restore lock before invoking this method.
     */
    public function delete(string $name, bool $confirmedLatest = false): void
    {
        $path = $this->pathFor($name); // Strict archive basename validation.
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('This backup is no longer available. Refresh the backup list.');
        }

        // Check again under the shared operation lock, not only when rendering the UI.
        $newest = $this->backups(1);
        if (($newest[0]['name'] ?? null) === $name && ! $confirmedLatest) {
            throw new RuntimeException('The newest backup requires additional confirmation before deletion.');
        }

        if (! @unlink($path)) {
            throw new RuntimeException('Could not delete the selected backup. Check file permissions or whether the archive is in use.');
        }
    }

    public function create(string $destination): string
    {
        $driver = $this->driver();
        if (file_exists($destination)) throw new RuntimeException('Backup already exists.');
        if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0700, true) && ! is_dir(dirname($destination))) {
            throw new RuntimeException('Cannot create backup directory.');
        }

        $snapshot = tempnam($this->folder(), 'certify-snapshot-');
        if ($snapshot === false) throw new RuntimeException('Cannot allocate temporary database snapshot.');
        $zip = new ZipArchive();
        $zipOpen = false;
        try {
            if ($driver === 'sqlite') {
                @unlink($snapshot); // SQLite VACUUM INTO needs a new path.
                $pdo = DB::connection()->getPdo();
                $pdo->exec("VACUUM INTO '".str_replace("'", "''", $snapshot)."'");
                $dbEntry = 'database/database.sqlite';
            } else {
                $this->exportMysql($snapshot);
                $dbEntry = 'database/mysql.sql';
            }
            if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Could not create ZIP archive.');
            }
            $zipOpen = true;
            $files = [$dbEntry => $snapshot];
            $private = Storage::disk('local')->path('private');
            if (is_dir($private)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($private, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $item) {
                    if (! $item->isFile() || $item->isLink()) continue;
                    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($private) + 1));
                    if (str_contains($relative, '..') || str_starts_with($relative, '/')) continue;
                    $files['storage/app/private/'.$relative] = $item->getPathname();
                }
            }
            $hashes = [];
            foreach ($files as $name => $file) {
                if (! $zip->addFile($file, $name)) throw new RuntimeException('Could not archive '.$name);
                $hashes[$name] = hash_file('sha256', $file);
            }
            $manifest = [
                'format' => 'certify-backup-2', 'driver' => $driver,
                'created_at' => now()->toIso8601String(), 'files' => $hashes,
                'note' => 'Contains private recipient data. Store securely. .env is not included.',
            ];
            if (! $zip->addFromString('certify-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Could not write backup manifest.');
            }
            if (! $zip->close()) throw new RuntimeException('Could not finish backup archive.');
            $zipOpen = false;
            return $destination;
        } catch (\Throwable $error) {
            if ($zipOpen) $zip->close();
            @unlink($destination);
            throw $error;
        } finally {
            @unlink($snapshot);
        }
    }

    /** Resolve MySQL clients used by XAMPP, MariaDB, or a normal PATH install. */
    private function binary(string $kind): string
    {
        $isDump = $kind === 'dump';
        $key = $isDump ? 'mysqldump_binary' : 'mysql_binary';
        $configured = trim((string) config('certify-backup.'.$key, ''));
        $filenames = $isDump ? ['mysqldump.exe', 'mariadb-dump.exe'] : ['mysql.exe', 'mariadb.exe'];
        if (PHP_OS_FAMILY !== 'Windows') {
            $filenames = $isDump ? ['mysqldump', 'mariadb-dump'] : ['mysql', 'mariadb'];
        }

        // Never silently ignore an explicit, invalid override.
        if ($configured !== '') {
            $configured = trim($configured, " \t\n\r\0\x0B\"'");
            $found = $this->findExecutable($configured);
            if ($found !== null) return $found;
            throw new RuntimeException('Configured MySQL backup tool was not found. Check '.($isDump ? 'CERTIFY_MYSQLDUMP_BINARY' : 'CERTIFY_MYSQL_BINARY').' in .env and clear Laravel configuration cache.');
        }

        $directories = [];
        if (PHP_OS_FAMILY === 'Windows') {
            // From C:\xampp\htdocs\certify, walk back to the XAMPP installation.
            $directories[] = dirname(base_path(), 2).'/mysql/bin';
            $directories[] = 'C:/xampp/mysql/bin';
            foreach (['ProgramFiles', 'ProgramFiles(x86)'] as $envKey) {
                $base = getenv($envKey);
                if (is_string($base) && $base !== '') {
                    foreach (['MySQL/MySQL Server 8.0/bin', 'MySQL/MySQL Server 8.4/bin', 'MariaDB 11.4/bin', 'MariaDB 10.11/bin'] as $sub) {
                        $directories[] = $base.'/'.$sub;
                    }
                }
            }
        }
        foreach ($directories as $directory) {
            foreach ($filenames as $filename) {
                $candidate = $directory.'/'.$filename;
                if (is_file($candidate)) return $candidate;
            }
        }
        foreach ($filenames as $filename) {
            $found = $this->findExecutable($filename);
            if ($found !== null) return $found;
        }

        throw new RuntimeException($isDump
            ? 'Cannot find mysqldump or mariadb-dump. Check C:\\xampp\\mysql\\bin, or set CERTIFY_MYSQLDUMP_BINARY in .env.'
            : 'Cannot find mysql or mariadb. Check C:\\xampp\\mysql\\bin, or set CERTIFY_MYSQL_BINARY in .env.');
    }

    private function findExecutable(string $name): ?string
    {
        if (is_file($name)) return $name;
        if (str_contains($name, '/') || str_contains($name, '\\')) return null;
        $path = (string) getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $candidate = rtrim(trim($directory, "\""), '\\/').DIRECTORY_SEPARATOR.$name;
            if (is_file($candidate)) return $candidate;
            if (PHP_OS_FAMILY === 'Windows' && ! str_ends_with(strtolower($name), '.exe') && is_file($candidate.'.exe')) return $candidate.'.exe';
        }
        return null;
    }

    private function mysqlCommand(string $kind): array
    {
        $db = (array) config('database.connections.mysql', []);
        $name = (string) ($db['database'] ?? '');
        if ($name === '') throw new RuntimeException('MySQL database name is missing. Check DB_DATABASE in .env.');
        $args = [$this->binary($kind)];
        if (! empty($db['unix_socket'])) {
            $args[] = '--socket='.(string) $db['unix_socket'];
        } else {
            $args[] = '--host='.(string) ($db['host'] ?? '127.0.0.1');
            $args[] = '--port='.(string) ($db['port'] ?? '3306');
        }
        $args[] = '--user='.(string) ($db['username'] ?? 'root');
        $args[] = '--default-character-set=utf8mb4';
        if ($kind === 'dump') {
            // --no-tablespaces avoids an unnecessary PROCESS permission requirement
            // common on XAMPP/MySQL 8 installations using limited database users.
            array_push($args, '--single-transaction', '--quick', '--hex-blob', '--add-drop-table', '--triggers', '--no-tablespaces');
        } else {
            $args[] = '--binary-mode';
        }
        $args[] = $name;
        return $args;
    }

    private function mysqlPasswordEnv(): array
    {
        // Never add the password to CLI arguments (visible in process listings).
        return ['MYSQL_PWD' => (string) config('database.connections.mysql.password', '')];
    }

    /** Read-only connection check using the same MySQL CLI as backups/restores. */
    public function mysqlDiagnostics(): string
    {
        if ($this->driver() !== 'mysql') throw new RuntimeException('MySQL checks are only available for MySQL installations.');
        $dumpBinary = $this->binary('dump');
        $clientBinary = $this->binary('import');
        foreach ([$dumpBinary, $clientBinary] as $bin) {
            $check = new Process([$bin, '--version']);
            $check->setTimeout(15);
            $check->run();
            if (! $check->isSuccessful()) throw new RuntimeException('MySQL tool could not start. Check your XAMPP MySQL installation and the .env executable paths.');
        }
        $command = $this->mysqlCommand('import');
        array_splice($command, -1, 0, ['--execute=SELECT 1']);
        $check = new Process($command, null, $this->mysqlPasswordEnv());
        $check->setTimeout(25);
        $check->run();
        if (! $check->isSuccessful()) {
            $this->mysqlFailure($check, 'connection');
        }
        return 'MySQL tools were located and a read-only connection succeeded. You can retry Create backup. Export permissions are checked during the actual backup.';
    }

    private function mysqlFailure(Process $process, string $action): never
    {
        $this->mysqlFailureText($process->getErrorOutput(), (int) $process->getExitCode(), $action);
    }

    private function mysqlFailureText(string $stderr, int $exitCode, string $action): never
    {
        $error = trim($stderr);
        $password = (string) config('database.connections.mysql.password', '');
        if ($password !== '') $error = str_replace($password, '[redacted]', $error);
        Log::warning('Certify MySQL '.$action.' failed', [
            'exit_code' => $exitCode,
            'error' => mb_substr($error, 0, 1200),
        ]);
        $lower = strtolower($error);
        if (str_contains($lower, 'process privilege') || str_contains($lower, 'access denied; you need')) {
            throw new RuntimeException('The MySQL account lacks an export permission. Grant backup access (usually SELECT and SHOW VIEW) to the configured database.');
        }
        if (str_contains($lower, 'access denied') || str_contains($lower, 'authentication failed')) {
            throw new RuntimeException('MySQL rejected the configured login or lacks required privileges. Check DB_USERNAME and DB_PASSWORD in .env, then run php artisan config:clear.');
        }
        if (str_contains($lower, "can't connect") || str_contains($lower, 'connection refused') || str_contains($lower, 'unknown mysql server host') || str_contains($lower, 'connection timed out')) {
            throw new RuntimeException('Cannot connect to MySQL. Start MySQL in XAMPP and verify DB_HOST and DB_PORT in .env.');
        }
        if (str_contains($lower, 'unknown option') || str_contains($lower, 'unknown variable')) {
            throw new RuntimeException('The installed MySQL dump tool does not support an export option. Check storage/logs/laravel.log or update the XAMPP MySQL client.');
        }
        if (str_contains($lower, 'no space left') || str_contains($lower, 'permission denied') || str_contains($lower, 'error 13') || str_contains($lower, "can't create")) {
            throw new RuntimeException('MySQL reported a file-write error. Check storage/logs/laravel.log for the exact file and MySQL error, then check free disk space and permissions.');
        }
        throw new RuntimeException('MySQL '.$action.' failed (exit code '.$exitCode.'). See storage/logs/laravel.log for the database tool error.');
    }

    /**
     * Stream mysqldump directly into a private file opened by PHP. On Windows,
     * a MySQL client may fail to open --result-file even when Laravel can write
     * its backup directory. Redirecting stdout through proc_open's file
     * descriptor avoids that issue without buffering an entire database in RAM.
     * The --hex-blob flag in mysqlCommand protects binary columns in SQL dumps.
     */
    private function exportMysql(string $path): void
    {
        $folder = dirname($path);
        if (! is_dir($folder) || ! is_writable($folder)) {
            throw new RuntimeException('Certify cannot write its backup folder. Check storage/app/backups permissions.');
        }
        if (! function_exists('proc_open')) {
            throw new RuntimeException('MySQL backup requires the PHP proc_open function. Check disable_functions in php.ini.');
        }

        $errorFile = tempnam($folder, 'certify-mysql-error-');
        if ($errorFile === false) {
            throw new RuntimeException('Certify cannot create a temporary backup diagnostic file. Check storage/app/backups permissions.');
        }
        $pipes = [];
        $process = false;
        $timedOut = false;
        $exitCode = -1;
        try {
            // Passing an argv array avoids shell interpretation and quoting bugs
            // when XAMPP is installed in a Windows path containing spaces.
            $env = getenv();
            if (! is_array($env)) $env = [];
            $env = array_merge($env, $this->mysqlPasswordEnv());
            $process = @proc_open($this->mysqlCommand('dump'), [
                0 => ['pipe', 'r'],
                1 => ['file', $path, 'wb'],
                2 => ['file', $errorFile, 'wb'],
            ], $pipes, null, $env, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('Certify could not launch mysqldump or open its output file. Check XAMPP tool paths and storage/app/backups permissions.');
            }
            if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

            $deadline = microtime(true) + 900;
            do {
                $state = proc_get_status($process);
                if (! ($state['running'] ?? false)) {
                    $exitCode = (int) ($state['exitcode'] ?? -1);
                    break;
                }
                if (microtime(true) >= $deadline) {
                    $timedOut = true;
                    proc_terminate($process);
                    break;
                }
                usleep(100000);
            } while (true);
            $closedCode = proc_close($process);
            $process = false;
            if ($exitCode < 0) $exitCode = $closedCode;

            $stderr = is_file($errorFile) ? (string) file_get_contents($errorFile, false, null, 0, 8192) : '';
            if ($timedOut) {
                throw new RuntimeException('MySQL backup timed out after 15 minutes. Check the application log and available disk space.');
            }
            if ($exitCode !== 0) {
                $this->mysqlFailureText($stderr, $exitCode, 'export');
            }
            clearstatcache(true, $path);
            if (! is_file($path) || filesize($path) === 0) {
                throw new RuntimeException('MySQL finished without producing SQL data. Check storage/logs/laravel.log and verify the backup folder has free space.');
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
            @unlink($errorFile);
        }
    }

    private function importMysql(string $path): void
    {
        $stream = fopen($path, 'rb');
        if (! $stream) throw new RuntimeException('Cannot open MySQL SQL snapshot.');
        try {
            $process = new Process($this->mysqlCommand('import'), null, $this->mysqlPasswordEnv());
            $process->setInput($stream);
            $process->setTimeout(900);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->mysqlFailure($process, 'restore');
            }
        } catch (\Throwable $e) {
            throw new RuntimeException('MySQL restore was interrupted. Your pre-restore safety backup is available for recovery.', 0, $e);
        } finally { fclose($stream); }
    }

    /** Checks all archive entries, hashes, supported paths, and database type before use. */
    public function verify(string $archive): array
    {
        $this->driver();
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Not a readable ZIP archive.');
        try {
            $contents = $zip->getFromName('certify-manifest.json');
            $manifest = is_string($contents) ? json_decode($contents, true) : null;
            $format = $manifest['format'] ?? '';
            if (! is_array($manifest) || ! in_array($format, ['certify-backup-1','certify-backup-2'], true) || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('This ZIP is not a supported Certify backup.');
            }
            $driver = $format === 'certify-backup-1' ? 'sqlite' : ($manifest['driver'] ?? '');
            if (! in_array($driver, ['sqlite', 'mysql'], true)) throw new RuntimeException('Invalid database type in backup.');
            $dbEntry = $driver === 'sqlite' ? 'database/database.sqlite' : 'database/mysql.sql';
            if (! isset($manifest['files'][$dbEntry]) || count($manifest['files']) > 10000 || count($manifest['files']) !== $zip->numFiles - 1) {
                throw new RuntimeException('Backup is missing the database snapshot or contains unexpected entries.');
            }
            $totalBytes = 0;
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if (isset($seen[$name])) throw new RuntimeException('Duplicate ZIP entry.');
                $seen[$name] = true;
                $totalBytes += (int) ($stat['size'] ?? 0);
                if ($totalBytes > 4 * 1024 * 1024 * 1024) throw new RuntimeException('Backup exceeds the 4 GB restore safety limit.');
                if ($name !== 'certify-manifest.json' && ! isset($manifest['files'][$name])) throw new RuntimeException('Unexpected ZIP entry.');
            }
            foreach ($manifest['files'] as $name => $expected) {
                $valid = $name === $dbEntry || preg_match('#\Astorage/app/private/(?:[A-Za-z0-9_. -]+/)*[A-Za-z0-9_. -]+\z#D', $name);
                if (! $valid || str_contains($name, '..') || ! preg_match('/\A[a-f0-9]{64}\z/D', (string) $expected)) {
                    throw new RuntimeException('Backup contains an unsafe entry name or checksum.');
                }
                $stream = $zip->getStream($name);
                if (! $stream) throw new RuntimeException('Backup is missing '.$name);
                try {
                    $hash = hash_init('sha256');
                    while (! feof($stream)) {
                        $chunk = fread($stream, 1024 * 1024);
                        if ($chunk === false) throw new RuntimeException('Unable to read backup entry.');
                        hash_update($hash, $chunk);
                    }
                    if (! hash_equals($expected, hash_final($hash))) throw new RuntimeException('Backup checksum mismatch: '.$name);
                } finally { fclose($stream); }
            }
            return ['files' => count($manifest['files']), 'created_at' => (string) ($manifest['created_at'] ?? 'Unknown'), 'driver' => $driver, 'bytes' => $totalBytes];
        } finally { $zip->close(); }
    }

    public function restore(string $archive): void
    {
        $details = $this->verify($archive);
        if ($details['driver'] !== $this->driver()) {
            throw new RuntimeException('Backup uses '.$details['driver'].' but Certify is configured for '.$this->driver().'. Switch your database configuration before restoring.');
        }
        $staging = storage_path('app/certify-restore-'.bin2hex(random_bytes(6)));
        if (! mkdir($staging, 0700, true) && ! is_dir($staging)) throw new RuntimeException('Could not create restore staging folder.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot open backup for restore.');
        try {
            $manifest = json_decode($zip->getFromName('certify-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach (array_keys($manifest['files']) as $name) {
                $target = $staging.'/'.$name;
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) throw new RuntimeException('Cannot stage backup.');
                $stream = $zip->getStream($name);
                $out = fopen($target, 'wb');
                if (! $stream || ! $out) throw new RuntimeException('Cannot stage backup entry.');
                try { if (stream_copy_to_stream($stream, $out) === false) throw new RuntimeException('Failed staging backup entry.'); }
                finally { fclose($stream); fclose($out); }
            }
            if ($details['driver'] === 'mysql') {
                DB::disconnect();
                $this->importMysql($staging.'/database/mysql.sql');
            } else {
                // Close the SQLite connection and atomically replace the snapshot (when supported).
                try { DB::connection()->statement('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (\Throwable $ignored) { /* No WAL mode */ }
                DB::disconnect();
                $dbPath = (string) config('database.connections.sqlite.database', database_path('database.sqlite'));
                if ($dbPath === ':memory:') throw new RuntimeException('Cannot restore an in-memory database.');
                if (! is_dir(dirname($dbPath))) mkdir(dirname($dbPath), 0700, true);
                $tempDestination = $dbPath.'.restore-'.bin2hex(random_bytes(4));
                if (! copy($staging.'/database/database.sqlite', $tempDestination)) throw new RuntimeException('Failed to stage the SQLite database.');
                $previous = $dbPath.'.before-restore-'.bin2hex(random_bytes(4));
                try {
                    if (is_file($dbPath) && ! rename($dbPath, $previous)) throw new RuntimeException('Could not move existing SQLite database. Stop other Certify processes.');
                    if (! rename($tempDestination, $dbPath)) throw new RuntimeException('Could not activate restored SQLite database.');
                    @unlink($previous);
                } catch (\Throwable $e) {
                    if (is_file($previous)) @rename($previous, $dbPath);
                    throw $e;
                } finally { @unlink($tempDestination); }
            }
            // Never write outside the private directory; keep unrelated local files intact.
            foreach (array_keys($manifest['files']) as $name) {
                if (! str_starts_with($name, 'storage/app/private/')) continue;
                $relative = substr($name, strlen('storage/app/'));
                $target = Storage::disk('local')->path($relative);
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) throw new RuntimeException('Cannot create private storage folder.');
                if (! copy($staging.'/'.$name, $target)) throw new RuntimeException('Failed restoring private data: '.$name);
            }
        } finally {
            $zip->close();
            if (is_dir($staging)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                @rmdir($staging);
            }
        }
    }
}
