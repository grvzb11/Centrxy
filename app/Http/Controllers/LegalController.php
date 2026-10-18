<?php

namespace App\Http\Controllers;

class LegalController
{
    /** One consolidated, factual policy page; no Legal Readiness checklist workflow. */
    public function index()
    {
        return response()->view('legal.index')->header('Cache-Control', 'no-store, private');
    }

    /** Preserve pre-existing deep links to individual policy pages. */
    public function document(string $slug)
    {
        abort_unless(in_array($slug, ['privacy','terms','cookies','accessibility','licenses'], true), 404);
        return redirect()->to(route('legal.index').'#'.$slug, 302);
    }
}
