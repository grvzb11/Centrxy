<?php

/** Standalone source-level regression check: no Certify-specific ZIP upload-size rule. */
$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/BackupController.php');
$view = file_get_contents($root.'/resources/views/settings/backup.blade.php');
if ($controller === false || $view === false) {
    fwrite(STDERR, "FAIL: Required source file is missing.\n");
    exit(1);
}
if (! str_contains($controller, "'archive' => ['required', 'file']") ||
    preg_match('/\bmax\s*:\s*262144\b/', $controller) ||
    str_contains($view, 'Up to 256 MB') ||
    ! str_contains($view, 'PHP upload settings')) {
    fwrite(STDERR, "FAIL: Fixed backup import limit is still active or upload guidance is missing.\n");
    exit(1);
}
fwrite(STDOUT, "PASS: Fixed 256 MiB backup import limit removed; PHP upload guidance retained.\n");
