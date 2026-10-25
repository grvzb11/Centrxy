<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        app(\App\Services\InitializeDesktopDemoHistory::class)->run();

        Window::open()
            ->hideMenu()
            ->maximized()
            ->webPreferences([
                'devTools' => false,
            ]);
    }

    /**
     * PHP configuration for Certrxy Desktop.
     */
    public function phpIni(): array
    {
        return [
            // Prevent the Windows OPcache/ASLR startup issue.
            'opcache.enable' => '0',
            'opcache.enable_cli' => '0',

            // Certificate PDF uploads.
            'file_uploads' => '1',
            'upload_max_filesize' => '20M',
            'post_max_size' => '25M',

            // Runtime configuration.
            'memory_limit' => '512M',
            'max_execution_time' => '120',
            'max_input_time' => '120',
        ];
    }
}
