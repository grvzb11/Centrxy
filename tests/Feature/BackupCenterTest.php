<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_backup_page_has_real_backup_controls_and_worker_link(): void
    {
        config(['database.default' => 'sqlite']);
        $this->get(route('settings.backup'))
            ->assertOk()
            ->assertSee('Create backup')
            ->assertSee('Upload &amp; verify', false)
            ->assertSee('Saved backups')
            ->assertSee(route('settings.background'));
    }

    public function test_backup_controls_are_restricted_to_local_loopback(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->get(route('settings.backup'))
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->post(route('settings.backup.create'))
            ->assertForbidden();
    }

    public function test_mysql_diagnostics_action_is_local_only(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->post(route('settings.backup.diagnose'))
            ->assertForbidden();
    }

    public function test_mysql_backup_page_has_diagnostics_button(): void
    {
        config(['database.default' => 'mysql']);
        $this->get(route('settings.backup'))
            ->assertOk()
            ->assertSee('Check MySQL tools')
            ->assertSee(route('settings.backup.diagnose'));
    }

    public function test_restore_requires_exact_confirmation_and_acknowledgment(): void
    {
        config(['database.default' => 'sqlite']);
        $this->post(route('settings.backup.restore', ['name' => 'Certify_example.zip']), [
            'confirmation' => 'RESTORE',
            'acknowledge' => '1',
        ])->assertSessionHasErrors('confirmation');

        $this->post(route('settings.backup.restore', ['name' => 'Certify_example.zip']), [
            'confirmation' => 'RESTORE CERTIFY',
        ])->assertSessionHasErrors('acknowledge');
    }
}
