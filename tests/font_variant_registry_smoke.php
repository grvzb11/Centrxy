<?php
// Standalone font-family registry test; runs without Laravel or Composer.
namespace Illuminate\Support\Facades {
    final class Storage {
        public static string $root;
        public static function disk($name) { return new class {
            public function path($path) { return \Illuminate\Support\Facades\Storage::$root.'/'.$path; }
            public function exists($path) { return is_file($this->path($path)); }
            public function get($path) { return file_get_contents($this->path($path)); }
        }; }
    }
}
namespace {
    require dirname(__DIR__).'/app/Support/FontLibrary.php';
    use Illuminate\Support\Facades\Storage;
    use App\Support\FontLibrary;
    Storage::$root = sys_get_temp_dir().'/certify-font-variant-'.bin2hex(random_bytes(6));
    try {
        $fontId = 'installed_'.str_repeat('a', 32);
        $regular = ['id' => $fontId, 'name' => 'Test Face', 'path' => 'private/fonts/source/'.$fontId.'.ttf', 'tcpdf_name' => 'sample_regular'];
        FontLibrary::register($regular);
        if (FontLibrary::face(FontLibrary::lookup($fontId), 'B') !== null) throw new \RuntimeException('Legacy font unexpectedly supports Bold');
        foreach (['B' => 'bold', 'I' => 'italic', 'BI' => 'bolditalic'] as $style => $name) {
            $face = ['path' => 'private/fonts/source/'.$fontId.'_'.strtolower($style).'.ttf', 'tcpdf_name' => 'sample_'.$name];
            if (! FontLibrary::addVariant($fontId, $style, $face)) throw new \RuntimeException('Cannot add '.$style);
            if (FontLibrary::face(FontLibrary::lookup($fontId), $style)['tcpdf_name'] !== 'sample_'.$name) throw new \RuntimeException('Wrong font face');
        }
        if (FontLibrary::face(FontLibrary::lookup($fontId), '')['tcpdf_name'] !== 'sample_regular') throw new \RuntimeException('Regular font changed');
        try { FontLibrary::addVariant($fontId, 'B', ['path' => 'untrusted', 'tcpdf_name' => 'bogus']); throw new \RuntimeException('Duplicate allowed'); }
        catch (\RuntimeException $e) { if ($e->getMessage() === 'Duplicate allowed') throw $e; }
        if (FontLibrary::face(FontLibrary::lookup($fontId), '../../foo') !== null) throw new \RuntimeException('Invalid style resolved');
        echo "PASS: Regular/Bold/Italic/Bold Italic registry, stable IDs, no replacement, invalid-style rejection\n";
    } finally {
        $dir=Storage::$root.'/private/fonts';
        if (is_file($dir.'/installed.json')) unlink($dir.'/installed.json');
        if (is_dir($dir)) rmdir($dir);
        if (is_dir(dirname($dir))) rmdir(dirname($dir));
        if (is_dir(Storage::$root)) rmdir(Storage::$root);
    }
}
