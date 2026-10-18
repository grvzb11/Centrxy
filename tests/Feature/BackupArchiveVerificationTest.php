<?php

namespace Tests\Feature;

use App\Services\CertifyBackupService;
use Tests\TestCase;
use ZipArchive;

class BackupArchiveVerificationTest extends TestCase
{
    private function makeArchive(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'certify-archive-test-');
        if ($path === false) {
            throw new \RuntimeException('Could not allocate test archive.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open test archive.');
        }
        $hashes = [];
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
            $hashes[$name] = hash('sha256', $content);
        }
        $zip->addFromString('certify-manifest.json', json_encode([
            'format' => 'certify-backup-2',
            'driver' => 'sqlite',
            'created_at' => '2026-10-09T00:00:00+08:00',
            'files' => $hashes,
        ], JSON_THROW_ON_ERROR));
        $zip->close();
        return $path;
    }

    public function test_verify_accepts_private_template_entries_without_regex_warning(): void
    {
        if (! extension_loaded('zip')) {
            $this->markTestSkipped('PHP zip extension is not available.');
        }
        config(['database.default' => 'sqlite']);
        $path = $this->makeArchive([
            'database/database.sqlite' => 'mock-db-bytes',
            'storage/app/private/templates/certificate.pdf' => 'template bytes',
            'storage/app/private/fonts/custom.ttf' => 'font bytes',
        ]);
        try {
            $result = (new CertifyBackupService())->verify($path);
            $this->assertSame('sqlite', $result['driver']);
            $this->assertSame(3, $result['files']);
        } finally {
            @unlink($path);
        }
    }

    public function test_verify_rejects_path_traversal(): void
    {
        if (! extension_loaded('zip')) {
            $this->markTestSkipped('PHP zip extension is not available.');
        }
        config(['database.default' => 'sqlite']);
        $path = $this->makeArchive([
            'database/database.sqlite' => 'mock-db-bytes',
            'storage/app/private/../.env' => 'unsafe bytes',
        ]);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('unsafe entry name or checksum');
            (new CertifyBackupService())->verify($path);
        } finally {
            @unlink($path);
        }
    }
}
