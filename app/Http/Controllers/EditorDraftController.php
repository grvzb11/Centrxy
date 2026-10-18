<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\EditorDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EditorDraftController
{
    public function show(Template $template): JsonResponse
    {
        abort_if($template->isArchived(), 404);
        return response()->json(['draft' => EditorDraftService::load($template)])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, Template $template): JsonResponse
    {
        abort_if($template->isArchived(), 404);
        $data = $request->validate([
            'baseline' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'state' => ['required', 'string', 'max:400000'],
        ]);
        EditorDraftService::write($template, $data['baseline'], $data['state']);
        return response()->json(['saved' => true]);
    }

    public function destroy(Template $template): JsonResponse
    {
        EditorDraftService::clear($template);
        return response()->json(['discarded' => true]);
    }
}
