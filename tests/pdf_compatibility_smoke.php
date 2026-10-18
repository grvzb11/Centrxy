<?php
// Standalone smoke test for the control flow when Composer is unavailable.
// Production TCPDF/FPDI and Windows qpdf behavior still require integration tests.
namespace Illuminate\Support\Facades {
    class Storage {
        public static function disk(string $disk): object {
            return new class {
                public function path(string $path): string { return \TestHarness\state::$temp.'/storage/'.$path; }
            };
        }
    }
}
namespace Symfony\Component\Process {
    class Process {
        private ?int $exit = null;
        private string $err = '', $out = '';
        public function __construct(private array $args) {}
        public function setTimeout(int $seconds): void {}
        public function run(): void {
            $io = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($this->args, $io, $pipes);
            if (!is_resource($proc)) { throw new \RuntimeException('failed to launch'); }
            $this->out = stream_get_contents($pipes[1]);
            $this->err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $this->exit = proc_close($proc);
        }
        public function isSuccessful(): bool { return $this->exit === 0; }
        public function getExitCode(): ?int { return $this->exit; }
        public function getErrorOutput(): string { return $this->err; }
        public function getOutput(): string { return $this->out; }
    }
}
namespace App\Pdf {
    class SafeFpdi {
        public function __construct(...$args) {}
        public function setSourceFile(string $path): void {
            $content = file_get_contents($path);
            if (str_contains($content, '/ObjStm')) {
                throw new \RuntimeException('compression technique unsupported by FPDI');
            }
            if (!str_starts_with($content, '%PDF-')) { throw new \RuntimeException('invalid PDF'); }
        }
        public function importPage(int $page): void {}
    }
}
namespace TestHarness {
    class state { public static string $temp; public static string $configuredQpdf = ''; }
    function assertTrue(bool $condition, string $message): void { if (!$condition) { throw new \RuntimeException($message); } }
}
namespace {
    function config(string $key, mixed $default = null): mixed {
        if ($key === 'certificates.qpdf_binary') return \TestHarness\state::$configuredQpdf;
        if ($key === 'certificates.ghostscript_binary') return 'missing-ghostscript';
        return $default;
    }
    function base_path(string $path = ''): string { return \TestHarness\state::$temp.'/project/'.$path; }
    require dirname(__DIR__).'/app/Support/PdfCompatibility.php';
    $temp = sys_get_temp_dir().'/certify-compat-smoke-'.bin2hex(random_bytes(5));
    \TestHarness\state::$temp = $temp;
    mkdir($temp.'/storage/private/templates',0777,true);
    mkdir($temp.'/project',0777,true);
    $qpdf = $temp.'/fake-qpdf.sh';
    $compatible = dirname(__DIR__).'/tests/Fixtures/compatible.pdf';
    \TestHarness\assertTrue(is_file($compatible),'compatible pdf fixture must exist');
    $modern = dirname(__DIR__).'/tests/Fixtures/modern_object_streams.pdf';
    $sourcePath = $temp.'/storage/private/templates/source.pdf';
    copy($modern,$sourcePath);
    $originalHash = hash_file('sha256',$sourcePath);
    $script = '#!/bin/sh' . "\n" .
              'if [ "$1" = "--version" ]; then echo "qpdf version 12.4.2"; exit 0; fi' . "\n" .
              'case " $* " in *--stream-data=preserve*) echo "preserve-path deliberately rejected" >&2; exit 2 ;; esac' . "\n" .
              '/bin/cp '.escapeshellarg($compatible).' "$6"' . "\n";
    file_put_contents($qpdf, $script);
    chmod($qpdf,0755);
    \TestHarness\state::$configuredQpdf = $qpdf;
    putenv('PATH='.$temp); // Prevent system Ghostscript from masking our failure fixture.
    $result = \App\Support\PdfCompatibility::importablePath($sourcePath);
    \TestHarness\assertTrue(is_file($result),'fallback must create a derived PDF');
    \TestHarness\assertTrue(str_contains($result, '.qpdf-uncompressed.pdf'),'second qpdf strategy should be used');
    \TestHarness\assertTrue(hash_file('sha256',$sourcePath) === $originalHash,'original must remain intact');
    echo "PASS: qpdf preserve fails; qpdf uncompress succeeds; original unchanged\n";
    \TestHarness\state::$configuredQpdf = 'missing-qpdf';
    $reused = \App\Support\PdfCompatibility::importablePath($sourcePath);
    \TestHarness\assertTrue($result === $reused, 'cached copy should work without converter');
    echo "PASS: cached import works without converter\n";
    $broken = $temp.'/storage/private/templates/broken.pdf';
    copy($modern,$broken);
    file_put_contents($broken, "\n%force-unique-cache-key", FILE_APPEND);
    $errscript = '#!/bin/sh' . "\n" .
                 'if [ "$1" = "--version" ]; then echo "qpdf version 12.4.2"; exit 0; fi' . "\n" .
                 'echo "qpdf: corrupt xref table" >&2; exit 2' . "\n";
    file_put_contents($qpdf,$errscript);
    \TestHarness\state::$configuredQpdf = $qpdf;
    try {
        \App\Support\PdfCompatibility::importablePath($broken);
        throw new \RuntimeException('expected failure for broken PDF');
    } catch (\RuntimeException $e) {
        \TestHarness\assertTrue(str_contains($e->getMessage(),'corrupt xref table'), 'diagnostics should expose root cause');
        \TestHarness\assertTrue(str_contains($e->getMessage(),'qpdf-uncompressed'), 'diagnostics should name converter fallback');
        echo "PASS: detailed converter failure is returned, not hidden\n";
    }
    $already = $temp.'/storage/private/templates/compatible.pdf';
    copy($compatible,$already);
    $res = \App\Support\PdfCompatibility::importablePath($already);
    \TestHarness\assertTrue($res === $already, 'compatible PDF should not need converter');
    echo "PASS: compatible PDF bypasses converter\n";
    echo "All 4 standalone PDF compatibility smoke tests passed\n";
    // Remove test resources (including derived cache) after assertions.
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($temp);
}
