<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Support\SignatureAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Signatures are private and deliberately limited to this localhost studio. */
final class SignatureController
{
    private function assertLocal(Request $request): void
    {
        abort_unless(app()->environment('local', 'testing') && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403,
            'Signature management is available only in the local Certify studio.');
    }

    public function store(Request $request, Template $template): JsonResponse
    {
        $this->assertLocal($request);
        abort_if($template->isArchived(), 403, 'Restore the template before editing signatures.');
        $request->validate([
            'signature' => 'required|file|max:2048|mimes:png,webp',
            'authorized' => 'required|accepted',
        ]);
        $file = $request->file('signature');
        $info = @getimagesize($file->getRealPath());
        if (!$info || !in_array($info[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || ($info[0] ?? 0) < 8 || ($info[1] ?? 0) < 8
            || ($info[0] ?? 0) > 2400 || ($info[1] ?? 0) > 1200
            || ($info[0] ?? 0) * ($info[1] ?? 0) > 2000000) {
            return response()->json(['message' => 'Use a valid PNG or WebP signature up to 2400 × 1200 pixels and 2 MB.'], 422);
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($info[2] === IMAGETYPE_WEBP) {
            if (!function_exists('imagecreatefromwebp') || !function_exists('imagepng')) {
                return response()->json(['message' => 'Enable the PHP GD extension to convert WebP signatures, or upload a PNG.'], 422);
            }
            $image = @imagecreatefromwebp($file->getRealPath());
            if (!$image) return response()->json(['message' => 'WebP signature could not be decoded.'], 422);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            $saved = imagepng($image, null, 6);
            $converted = ob_get_clean();
            imagedestroy($image);
            if (!$saved || !is_string($converted)) return response()->json(['message' => 'Could not convert the WebP signature.'], 422);
            $bytes = $converted;
        }
        if (!is_string($bytes) || strlen($bytes) > 3 * 1024 * 1024) {
            return response()->json(['message' => 'The uploaded signature is too large.'], 422);
        }
        $id = str_replace('-', '', (string) Str::uuid());
        if (!Storage::disk('local')->put(SignatureAssets::path($id), $bytes)) {
            return response()->json(['message' => 'Cannot save signature. Check storage permissions.'], 500);
        }
        return response()->json(['id' => $id, 'url' => route('signatures.show', ['id' => $id])], 201);
    }

    public function show(Request $request, string $id)
    {
        $this->assertLocal($request);
        abort_unless(SignatureAssets::exists($id), 404);
        return Storage::disk('local')->response(SignatureAssets::path($id), 'signature.png', [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
