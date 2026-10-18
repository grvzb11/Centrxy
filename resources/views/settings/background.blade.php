@extends('layouts.app')
@section('hide_top','1')
@section('title','Background processing')
@push('styles')<link rel="stylesheet" href="{{ asset('css/certify-backup-231.css') }}?v=2.0.31">@endpush
@section('content')
<div class="certify-backup">
    <header class="cb-header">
        <div>
            <div class="cb-eyebrow">OPTIONAL AUTOMATION</div>
            <h1>Background processing</h1>
            <p>Complete pending certificate batches even when the browser tab is closed.</p>
        </div>
        <a class="cb-button cb-button-outline" href="{{ route('settings.backup') }}">← Back to backups</a>
    </header>
    <section class="cb-card cb-help-card">
        <h2>Windows scheduled worker</h2>
        <p>From PowerShell in your Certrxy project folder, run the following command to enable the local worker:</p>
        <pre>powershell -ExecutionPolicy Bypass -File .\desktop\Install-Background-Worker.ps1</pre>
        <p>To disable it before restoring a backup:</p>
        <pre>powershell -ExecutionPolicy Bypass -File .\desktop\Install-Background-Worker.ps1 -Remove</pre>
        <p>This optional task runs once per minute. Do not run it during database restoration. For data safety, close other Certrxy windows before restoring.</p>
    </section>
</div>
@endsection
