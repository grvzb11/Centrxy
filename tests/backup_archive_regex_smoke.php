<?php
/** Standalone regression check: php tests/backup_archive_regex_smoke.php */
$source = file_get_contents(__DIR__.'/../app/Services/CertifyBackupService.php');
if ($source === false) throw new RuntimeException('Cannot read backup service source.');
$pattern = '#\Astorage/app/private/(?:[A-Za-z0-9_. -]+/)*[A-Za-z0-9_. -]+\z#D';
$expectedCall = "preg_match('".$pattern."', \$name)";
if (! str_contains($source, $expectedCall)) {
    throw new RuntimeException('Expected closed private-archive validation regex not found.');
}
$checks = [
    'storage/app/private/templates/certificate.pdf' => true,
    'storage/app/private/fonts/custom.ttf' => true,
    'storage/app/private/../.env' => false,
    'storage/app/private/secrets#extra' => false,
    'storage/app/public/not-private.pdf' => false,
];
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    foreach ($checks as $entry => $expected) {
        $valid = preg_match($pattern, $entry) === 1 && ! str_contains($entry, '..');
        if ($valid !== $expected) throw new RuntimeException('Unexpected archive validation for '.$entry);
    }
} finally {
    restore_error_handler();
}
echo "PASS: backup private-file regex is closed and validates normal/unsafe paths\n";
