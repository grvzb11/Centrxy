@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'Dashboard')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-dashboard-210.css') }}?v=2.0.20">
@endpush
@section('content')
<div class="dash-home">
    <h1 class="dash-sr-only">Certrxy dashboard</h1>

    <section class="dash-hero" aria-labelledby="dashHeroTitle">
        <div class="dash-hero-copy">
            <span class="dash-hero-eyebrow">CERTIFICATE STUDIO</span>
            <h2 id="dashHeroTitle">Create certificates with ease.</h2>
            <p>Choose a template, add recipients, and export print-ready PDFs.</p>
            <div class="dash-hero-actions">
                <a class="dash-primary-action" href="{{ route('batches.create') }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Generate certificates
                </a>
                <a class="dash-secondary-action" href="{{ route('templates.index') }}#upload">Upload template <span aria-hidden="true">↗</span></a>
            </div>
        </div>
        <div class="dash-hero-art" aria-hidden="true">
            <img class="dash-hero-logo" src="{{ asset('assets/certrxy-lockup-wide-20261018.svg') }}" alt="Certrxy logo" decoding="async">
        </div>
    </section>

    <section class="dash-metrics" aria-label="Certificate workspace statistics">
        <div class="dash-metric">
            <span class="dash-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
            </span>
            <div class="dash-metric-text"><span>Saved templates</span><strong>{{ number_format($templates) }}</strong></div>
        </div>
        <div class="dash-metric">
            <span class="dash-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 4v16M12 9h5M12 13h5"/></svg>
            </span>
            <div class="dash-metric-text"><span>Generated batches</span><strong>{{ number_format($batches) }}</strong></div>
        </div>
        <div class="dash-metric">
            <span class="dash-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h14v16l-7-3-7 3z"/><path d="m9 10 2 2 4-4"/></svg>
            </span>
            <div class="dash-metric-text"><span>Certificates generated</span><strong>{{ number_format($issued) }}</strong></div>
        </div>
    </section>

    <div class="dash-columns">
        <section class="dash-panel" aria-labelledby="dashBatchesTitle">
            <header class="dash-panel-header">
                <div>
                    <h2 id="dashBatchesTitle">Recent batches</h2>
                    <p>View or download your recent certificates.</p>
                </div>
                <a class="dash-panel-link" href="{{ route('batches.index') }}">View history <span aria-hidden="true">→</span></a>
            </header>
            <div class="dash-batch-list">
                @forelse($recent as $batch)
                    @php
                        $readyCount = (int) $batch->completed_count;
                        $totalCount = (int) $batch->total_count;
                        $isFinished = $batch->status === 'completed';
                        $needsAttention = in_array($batch->status, ['failed', 'partial'], true);
                        $stateLabel = $isFinished ? 'Completed' : ($needsAttention ? 'Needs attention' : 'In progress');
                    @endphp
                    <article class="dash-batch-item">
                        <span class="dash-batch-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h4M9 13h6M9 17h6"/></svg>
                        </span>
                        <div class="dash-batch-details">
                            <a class="dash-item-name" href="{{ route('batches.show', $batch) }}" title="{{ $batch->title }}">{{ $batch->title }}</a>
                            <div class="dash-item-meta">
                                <span>{{ $readyCount }} of {{ $totalCount }} ready</span>
                                @if($batch->created_at)
                                    <span class="dash-meta-divider" aria-hidden="true">·</span>
                                    <time datetime="{{ $batch->created_at->toIso8601String() }}" title="{{ $batch->created_at->format('M j, Y · g:i A') }}">{{ $batch->created_at->format('M j, Y') }}</time>
                                @endif
                            </div>
                        </div>
                        <div class="dash-batch-actions">
                            <span class="dash-state {{ $needsAttention ? 'dash-state-warning' : ($isFinished ? 'dash-state-done' : 'dash-state-progress') }}">{{ $stateLabel }}</span>
                            @if($readyCount > 0)
                                <a class="dash-download" href="{{ route('batches.download', $batch) }}" title="Download ready certificates from {{ $batch->title }}">Download ZIP <span aria-hidden="true">↓</span></a>
                            @else
                                <a class="dash-open" href="{{ route('batches.show', $batch) }}" aria-label="View batch {{ $batch->title }}">View <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="dash-empty-state">
                        <span class="dash-empty-symbol" aria-hidden="true">▤</span>
                        <h3>No batches yet</h3>
                        <p>Generated certificates will appear here.</p>
                        <a href="{{ route('batches.create') }}">Create your first batch <span aria-hidden="true">→</span></a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="dash-panel dash-templates" aria-labelledby="dashTemplatesTitle">
            <header class="dash-panel-header">
                <div>
                    <h2 id="dashTemplatesTitle">Your templates</h2>
                    <p>Continue with one of your saved designs.</p>
                </div>
                <a class="dash-panel-link" href="{{ route('templates.index') }}">Browse all <span aria-hidden="true">→</span></a>
            </header>
            <div class="dash-template-list">
                @forelse($recentTemplates as $template)
                    @php
                        $isPdf = $template->mime_type === 'application/pdf';
                        $width = max(1, (float) $template->width_mm);
                        $height = max(1, (float) $template->height_mm);
                        $orientation = $width >= $height ? 'Landscape' : 'Portrait';
                        $format = $isPdf ? 'PDF' : ($template->mime_type === 'image/png' ? 'PNG' : ($template->mime_type === 'image/jpeg' ? 'JPG' : 'Image'));
                        $long = max($width, $height);
                        $short = min($width, $height);
                        $sizeLabel = abs($long - 297) < 2 && abs($short - 210) < 2 ? 'A4' : (abs($long - 279.4) < 2 && abs($short - 215.9) < 2 ? 'Letter' : 'Custom');
                        $fileUrl = route('templates.file', $template);
                    @endphp
                    <article class="dash-template-item">
                        <div class="dash-preview" aria-hidden="true">
                            <div class="dash-preview-paper" style="aspect-ratio: {{ $width }} / {{ $height }}">
                                @if($isPdf)
                                    <iframe loading="lazy" tabindex="-1" title="" src="{{ $fileUrl }}#toolbar=0&navpanes=0&view=Fit"></iframe>
                                @else
                                    <img loading="lazy" src="{{ $fileUrl }}" alt="">
                                @endif
                            </div>
                        </div>
                        <div class="dash-template-details">
                            <a class="dash-item-name" href="{{ route('templates.edit', $template) }}" title="{{ $template->name }}">{{ $template->name }}</a>
                            <div class="dash-item-meta">{{ $format }} <span aria-hidden="true">·</span> {{ $sizeLabel }} {{ $orientation }}</div>
                        </div>
                        <a class="dash-use-template" href="{{ route('batches.create', ['template' => $template->id]) }}" aria-label="Use template {{ $template->name }} to generate certificates">Use <span aria-hidden="true">→</span></a>
                    </article>
                @empty
                    <div class="dash-empty-state">
                        <span class="dash-empty-symbol" aria-hidden="true">▧</span>
                        <h3>No templates yet</h3>
                        <p>Upload a design to start creating certificates.</p>
                        <a href="{{ route('templates.index') }}#upload">Upload a template <span aria-hidden="true">→</span></a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
