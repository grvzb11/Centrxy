<?php

namespace App\Support;

use App\Pdf\SafeFpdi;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Preserve original PDF artwork while making modern object/xref streams
 * importable by FPDI's free parser. qpdf is preferred: it rewrites the PDF
 * structure without re-rendering the page. Ghostscript is a fallback only.
 *
 * No remote service is used. Source files are never modified. Derived copies
 * live in private storage and are cached by source SHA-256 digest.
 */
final class PdfCompatibility
{
    public static function importablePath(string $originalPath): string
    {
        if (! is_file($originalPath) || ! is_readable($originalPath)) {
            throw new RuntimeException('The source PDF template is missing or cannot be read.');
        }

        // Most standard PDF designs work directly; don't require a converter.
        try {
            self::probe($originalPath);
            return $originalPath;
        } catch (Throwable $error) {
            if (! self::isUnsupportedCompression($error)) {
                throw $error;
            }
        }

        $hash = hash_file('sha256', $originalPath);
        if (! is_string($hash)) {
            throw new RuntimeException('The source PDF template could not be checked.');
        }
        $cacheDirectory = Storage::disk('local')->path('private/pdf-compatible');
        if (! is_dir($cacheDirectory) && ! @mkdir($cacheDirectory, 0775, true) && ! is_dir($cacheDirectory)) {
            throw new RuntimeException('Cannot create the PDF compatibility cache. Check local storage permissions.');
        }

        // Cached derivatives remain usable even when the converter later becomes
        // unavailable (for example after a desktop upgrade).
        $qpdf = self::qpdfBinary();
        $ghostscript = self::ghostscriptBinary();
        $cachePaths = [
            'qpdf' => $cacheDirectory.DIRECTORY_SEPARATOR.$hash.'.qpdf.pdf',
            'qpdf-uncompressed' => $cacheDirectory.DIRECTORY_SEPARATOR.$hash.'.qpdf-uncompressed.pdf',
            'ghostscript' => $cacheDirectory.DIRECTORY_SEPARATOR.$hash.'.gs.pdf',
            'legacy' => $cacheDirectory.DIRECTORY_SEPARATOR.$hash.'.pdf',
        ];
        $strategies = [];
        if ($qpdf !== null) {
            // First preserve all existing stream data. If an unusual file
            // still cannot be read by FPDI, try uncompressing its streams.
            // Both methods keep existing PDF pages as PDF, not bitmaps.
            $strategies[] = ['qpdf', $qpdf, $cachePaths['qpdf']];
            $strategies[] = ['qpdf-uncompressed', $qpdf, $cachePaths['qpdf-uncompressed']];
        }
        if ($ghostscript !== null) {
            $strategies[] = ['ghostscript', $ghostscript, $cachePaths['ghostscript']];
        }

        // Several recipients may use the same design; serialize conversion.
        $lock = @fopen($cacheDirectory.DIRECTORY_SEPARATOR.$hash.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot lock the private PDF compatibility cache.');
        }
        $failures = [];
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock the private PDF compatibility cache.');
            }
            foreach ($cachePaths as $cachedPath) {
                if (self::isImportableCached($cachedPath)) {
                    return $cachedPath;
                }
            }
            foreach ($strategies as [$kind, $binary, $cachedPath]) {
                try {
                    self::convert($kind, $binary, $originalPath, $cachedPath);
                    return $cachedPath;
                } catch (Throwable $error) {
                    // Do not discard the root cause, as previous versions did.
                    // Different converters may still be able to process a file.
                    $reason = self::safeError($error->getMessage(), [$originalPath, $cachedPath]);
                    $failures[] = $kind.': '.$reason;
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if ($strategies === []) {
            throw new RuntimeException(self::missingConverterMessage());
        }
        throw new RuntimeException(
            'PDF compatibility conversion failed. '.implode(' | ', $failures)
            .' The original template was preserved. Run php artisan certify:pdf:doctor for converter diagnostics.'
        );
    }

    /** Keep errors readable and avoid leaking temporary or original file paths. */
    private static function safeError(string $error, array $paths): string
    {
        foreach ($paths as $path) {
            if ($path !== '') {
                $error = str_replace([$path, str_replace('\\', '/', $path)], '[PDF file]', $error);
            }
        }
        $error = trim(preg_replace('/\s+/u', ' ', $error) ?? $error);
        return substr($error, 0, 450);
    }

    public static function missingConverterMessage(): string
    {
        return 'This PDF contains modern object-stream compression. Certify can keep the original design, but generation requires its PDF compatibility engine. For Windows Desktop, run desktop/Prepare-PdfEngine.ps1 on the build computer and rebuild the installer; recipients do not install anything. For Laravel development, make qpdf available or export a standard unencrypted PDF 1.4.';
    }

    public static function isUnsupportedCompression(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());
        return str_contains($message, 'compression technique')
            || str_contains($message, 'compressed cross')
            || str_contains($message, 'object stream')
            || str_contains($message, 'xref stream');
    }

    /** Detect qpdf bundled with the installer, configured locally, or on PATH. */
    public static function qpdfBinary(): ?string
    {
        $bundledPath = base_path('resources/bin/win-x64/qpdf/bin/qpdf.exe');
        $configured = trim((string) config('certificates.qpdf_binary', ''));
        $candidates = array_filter([
            $configured,
            PHP_OS_FAMILY === 'Windows' && is_file($bundledPath) ? $bundledPath : '',
            PHP_OS_FAMILY === 'Windows' ? 'qpdf.exe' : 'qpdf',
        ]);
        return self::firstWorkingBinary($candidates, ['--version']);
    }

    /** Ghostscript remains supported for development and legacy installations. */
    public static function ghostscriptBinary(): ?string
    {
        $configured = trim((string) config('certificates.ghostscript_binary', ''));
        $candidates = array_filter(array_merge(
            [$configured],
            PHP_OS_FAMILY === 'Windows' ? ['gswin64c.exe', 'gswin32c.exe', 'gs.exe'] : ['gs'],
        ));
        return self::firstWorkingBinary($candidates, ['-version']);
    }

    private static function firstWorkingBinary(array $candidates, array $args): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        foreach (array_unique($candidates) as $candidate) {
            if (! is_string($candidate) || $candidate === '' || preg_match('/[\r\n\0]/', $candidate)) {
                continue;
            }
            try {
                $process = new Process(array_merge([$candidate], $args));
                $process->setTimeout(8);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (Throwable) {
                // An unavailable executable is not a fatal upload error.
            }
        }
        return null;
    }

    private static function convert(string $kind, string $binary, string $source, string $destination): void
    {
        $temp = $destination.'.'.bin2hex(random_bytes(8)).'.tmp.pdf';
        try {
            $command = match ($kind) {
                'qpdf' => [
                    $binary, '--warning-exit-0', '--object-streams=disable',
                    '--stream-data=preserve', '--force-version=1.4', $source, $temp,
                ],
                'qpdf-uncompressed' => [
                    $binary, '--warning-exit-0', '--object-streams=disable',
                    '--stream-data=uncompress', '--force-version=1.4', $source, $temp,
                ],
                default => [
                    $binary, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                    '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
                    '-sOutputFile='.$temp, $source,
                ],
            };
            $process = new Process($command);
            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful()) {
                $details = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException(
                    'process exited '.$process->getExitCode()
                    .($details !== '' ? ': '.$details : ' without diagnostic output')
                );
            }
            if (! self::hasPdfHeader($temp)) {
                throw new RuntimeException('converter did not create a nonempty PDF output');
            }
            if (filesize($temp) > 80 * 1024 * 1024) {
                throw new RuntimeException('normalized PDF exceeds the 80 MB compatibility cache limit');
            }
            try {
                // A successful qpdf run alone is not sufficient; FPDI itself
                // must be able to read the output before it becomes a cache.
                self::probe($temp);
            } catch (Throwable $error) {
                throw new RuntimeException('output still rejected by FPDI: '.$error->getMessage(), 0, $error);
            }
            if (is_file($destination)) {
                @unlink($destination);
            }
            if (! @rename($temp, $destination)) {
                throw new RuntimeException('unable to cache the normalized PDF');
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private static function isImportableCached(string $path): bool
    {
        if (! self::hasPdfHeader($path)) {
            return false;
        }
        try {
            self::probe($path);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function hasPdfHeader(string $path): bool
    {
        if (! is_file($path) || filesize($path) < 100) {
            return false;
        }
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }

    private static function probe(string $path): void
    {
        $pdf = new SafeFpdi('L', 'mm', [297, 210], true, 'UTF-8', false);
        $pdf->setSourceFile($path);
        $pdf->importPage(1);
    }
}
