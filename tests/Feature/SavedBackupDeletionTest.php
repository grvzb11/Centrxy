<?php

namespace Tests\Feature;

use App\Services\CertifyBackupService;
use Tests\TestCase;

class SavedBackupDeletionTest extends TestCase
{
    private string $originalStorage;
    private string $testStorage;
    private CertifyBackupService $backups;

    protected function setUp(): void
    {
        parent::setUp();
        // The maintenance actions are explicitly restricted to a local installation.
        $this->app['env'] = 'local';
        $this->originalStorage = $this->app->storagePath();
        $this->testStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'certify-backup-delete-'.bin2hex(random_bytes(8));
        mkdir($this->testStorage.DIRECTORY_SEPARATOR.'app', 0700, true);
        $this->app->useStoragePath($this->testStorage);
        $this->backups = new CertifyBackupService();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        // The isolated test folder contains only test fixtures, never the application's actual files.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testStorage, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->testStorage);
        parent::tearDown();
    }

    private function archive(string $name, int $modified): string
    {
        $path = $this->backups->pathFor($name);
        file_put_contents($path, 'saved test backup');
        touch($path, $modified);
        clearstatcache(true, $path);
        return $path;
    }

    public function test_backup_deletion_is_local_only(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.70'])
            ->delete(route('settings.backup.destroy', ['name' => 'Certify_missing.zip']), [
                'confirmation' => 'DELETE BACKUP',
            ])->assertForbidden();
    }

    public function test_deleting_an_old_archive_only_removes_that_archive(): void
    {
        $old = $this->archive('Certify_older_test.zip', time() - 3600);
        $new = $this->archive('Certify_newer_test.zip', time());
        $private = $this->testStorage.'/app/private-live.pdf';
        file_put_contents($private, 'live certificate');

        $this->delete(route('settings.backup.destroy', ['name' => basename($old)]), [
            'confirmation' => 'DELETE BACKUP',
        ])->assertRedirect(route('settings.backup'));

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($new);
        $this->assertFileExists($private);
    }

    public function test_newest_archive_requires_extra_server_side_confirmation(): void
    {
        $path = $this->archive('Certify_latest_test.zip', time());
        $route = route('settings.backup.destroy', ['name' => basename($path)]);

        $this->delete($route, ['confirmation' => 'DELETE BACKUP'])
            ->assertSessionHasErrors('acknowledge_latest');
        $this->assertFileExists($path);

        $this->delete($route, [
            'confirmation' => 'DELETE BACKUP',
            'acknowledge_latest' => '1',
            'latest_confirmation' => 'DELETE BACKUP',
        ])->assertSessionHasErrors('latest_confirmation');
        $this->assertFileExists($path);

        $this->delete($route, [
            'confirmation' => 'DELETE BACKUP',
            'acknowledge_latest' => '1',
            'latest_confirmation' => 'DELETE LATEST BACKUP',
        ])->assertRedirect(route('settings.backup'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_wrong_confirmation_does_not_delete_archive(): void
    {
        $path = $this->archive('Certify_confirm_test.zip', time());
        $this->delete(route('settings.backup.destroy', ['name' => basename($path)]), [
            'confirmation' => 'DELETE',
        ])->assertSessionHasErrors('confirmation');
        $this->assertFileExists($path);
    }

    public function test_restore_marker_blocks_backup_deletion(): void
    {
        $path = $this->archive('Certify_marker_test.zip', time());
        file_put_contents($this->testStorage.'/app/certify-restore-in-progress', 'restoring');
        $this->delete(route('settings.backup.destroy', ['name' => basename($path)]), [
            'confirmation' => 'DELETE BACKUP',
            'acknowledge_latest' => '1',
            'latest_confirmation' => 'DELETE LATEST BACKUP',
        ])->assertSessionHasErrors('backup');
        $this->assertFileExists($path);
    }

    public function test_symlink_named_like_backup_does_not_delete_external_target(): void
    {
        $outside = $this->testStorage.'/app/live-data.zip';
        file_put_contents($outside, 'do not remove');
        $link = $this->backups->pathFor('Certify_symlink_test.zip');
        if (! @symlink($outside, $link)) {
            $this->markTestSkipped('Creating symbolic links is not permitted on this operating system.');
        }
        $this->delete(route('settings.backup.destroy', ['name' => basename($link)]), [
            'confirmation' => 'DELETE BACKUP',
        ])->assertSessionHasErrors('backup');
        $this->assertFileExists($outside);
    }
}
