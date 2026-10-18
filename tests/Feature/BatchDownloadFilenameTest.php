<?php

namespace Tests\Feature;

use App\Support\BatchDownloadFilename;
use PHPUnit\Framework\TestCase;

class BatchDownloadFilenameTest extends TestCase
{
    public function test_batch_export_names_are_safe_and_readable(): void
    {
        $this->assertSame('Demo Landscape Certificate.zip', BatchDownloadFilename::forTitle('Demo Landscape Certificate'));
        $this->assertSame('Demo Landscape Certificate - Selected Certificates.zip', BatchDownloadFilename::forTitle('Demo Landscape Certificate', 'zip', ' - Selected Certificates'));
        $this->assertSame('Demo Landscape Certificate - A4 Print Sheets.pdf', BatchDownloadFilename::forTitle('Demo Landscape Certificate', 'pdf', ' - A4 Print Sheets'));
        $this->assertSame('Competition Awards Finals.zip', BatchDownloadFilename::forTitle('Competition: Awards/Finals?'));
        $this->assertSame('Batch - CON.zip', BatchDownloadFilename::forTitle('CON'));
        $this->assertSame('Certificate Batch.zip', BatchDownloadFilename::forTitle(' .. '));
        $this->assertSame('Araw ng Pagkilala.zip', BatchDownloadFilename::forTitle('Araw ng Pagkilala'));
    }
}
