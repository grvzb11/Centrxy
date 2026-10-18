<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;

class DashboardController
{
    public function index()
    {
        return view('dashboard.index', [
            'templates' => Template::active()->count(),
            'batches' => Batch::count(),
            'issued' => Certificate::where('status', 'issued')->whereHas('batch')->count(),
            'recent' => Batch::latest()->limit(5)->get(),
            'recentTemplates' => Template::active()->latest()->limit(3)->get(),
        ]);
    }
}
