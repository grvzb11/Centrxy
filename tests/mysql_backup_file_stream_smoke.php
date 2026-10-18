<?php
/** Standalone smoke test: php tests/mysql_backup_file_stream_smoke.php */
$path = tempnam(sys_get_temp_dir(), 'certify-stream-test-');
$error = tempnam(sys_get_temp_dir(), 'certify-stream-error-');
if (!$path || !$error) { throw new RuntimeException('Cannot allocate temporary files.'); }
try {
    // Exercise the same argv-array + binary file-descriptor mechanism as Certify.
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-r', 'fwrite(STDOUT, "-- simulated SQL dump\nCREATE TABLE test (id INT);\n");'], [
        0 => ['pipe', 'r'], 1 => ['file', $path, 'wb'], 2 => ['file', $error, 'wb'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Failed to launch simulated SQL writer.');
    if (isset($pipes[0])) fclose($pipes[0]);
    $lastCode = -1;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) { $lastCode = $status['exitcode']; break; }
        usleep(10000);
    } while (true);
    $closeCode = proc_close($process);
    if ($lastCode < 0) $lastCode = $closeCode;
    if ($lastCode !== 0 || !str_contains(file_get_contents($path), 'CREATE TABLE test')) {
        throw new RuntimeException('SQL streaming to a PHP-managed file failed.');
    }
    echo "PASS: command-array stdout stream writes SQL to disk without shell redirection\n";
} finally { @unlink($path); @unlink($error); }
