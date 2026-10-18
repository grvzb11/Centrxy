@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'Your certificates')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-history-208.css') }}?v=2.0.8">
<link rel="stylesheet" href="{{ asset('css/certify-trash-274.css') }}?v=2.0.74">
@endpush
@section('content')
<div class="history-premium" id="historyPage">
    <header class="history-premium-header">
        <div class="history-premium-intro">
            <h1>Your certificates</h1>
            <p class="history-premium-subtitle">All your generated batches, easy to find and revisit.</p>
            <div class="history-premium-totals" aria-label="Your certificate library totals">
                <span><strong>{{ number_format($totalBatches) }}</strong> {{ $totalBatches === 1 ? 'batch' : 'batches' }}</span>
                <span class="history-premium-totals-separator" aria-hidden="true"></span>
                <span><strong>{{ number_format($totalPdfs) }}</strong> {{ $totalPdfs === 1 ? 'certificate' : 'certificates' }}</span>
            </div>
        </div>
        <a href="{{ route('batches.create') }}" class="history-premium-new"><span aria-hidden="true">+</span> New batch</a>
    </header>

    <nav class="history-trash-tabs" aria-label="History views">
        <a href="{{ route('batches.index') }}" class="is-active" aria-current="page">All certificates</a>
        <a href="{{ route('batches.index', ['view' => 'trash']) }}">Trash @if($trashedCount > 0)<span>{{ $trashedCount }}</span>@endif</a>
    </nav>
    @if($totalBatches > 0)
    <form method="GET" action="{{ route('batches.index') }}" id="historyFilters" class="history-premium-filters" role="search" aria-label="Find certificate batches">
        <label class="history-premium-search">
            <span class="history-premium-sr">Search by batch or template name</span>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input id="historySearch" name="q" type="search" value="{{ $search }}" maxlength="120" placeholder="Search batches..." autocomplete="off">
        </label>
        <label class="history-premium-select">
            <span class="history-premium-sr">Filter by status</span>
            <select id="historyStatus" name="status">
                <option value="all" @selected($status === 'all')>All statuses</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
                <option value="processing" @selected($status === 'processing')>In progress</option>
                <option value="partial" @selected($status === 'partial')>Partially complete</option>
                <option value="failed" @selected($status === 'failed')>Failed</option>
            </select>
        </label>
        <noscript><button type="submit" class="btn secondary">Search</button></noscript>
        @if($search !== '' || $status !== 'all')
            <a class="history-premium-clear" href="{{ route('batches.index') }}" id="historyClear">Clear</a>
        @endif
    </form>
    @endif

    <div id="historyResults" aria-live="polite" aria-atomic="false">
        @if($totalBatches === 0)
            <section class="history-premium-empty">
                <div class="history-premium-empty-mark" aria-hidden="true">▤</div>
                <h2>No certificates generated yet</h2>
                <p>When you create a batch, it will appear here.</p>
                <a href="{{ route('batches.create') }}" class="btn">Generate certificates</a>
            </section>
        @elseif($batches->isEmpty())
            <section class="history-premium-empty">
                <h2>No matching batches</h2>
                <p>Try a different name or status.</p>
                <a href="{{ route('batches.index') }}" class="history-premium-clear">Clear filters</a>
            </section>
        @else
            <section class="history-premium-list" aria-label="Saved batches">
                @foreach($batches as $batch)
                    @php
                        $statusLabel = match ($batch->status) {
                            'completed' => 'Completed',
                            'failed' => 'Failed',
                            'partial' => 'Partially complete',
                            'processing' => 'In progress',
                            default => ucfirst((string) $batch->status),
                        };
                        $statusClass = match ($batch->status) {
                            'completed' => 'completed',
                            'failed' => 'failed',
                            'partial' => 'partial',
                            default => 'processing',
                        };
                        $progress = $batch->total_count > 0
                            ? round(($batch->completed_count + $batch->failed_count) / $batch->total_count * 100)
                            : 0;
                        $snapshot = $batch->template_snapshot ?? [];
                        $portrait = (float) ($snapshot['height_mm'] ?? 0) > (float) ($snapshot['width_mm'] ?? 0);
                    @endphp
                    <article class="history-premium-row">
                        <div class="history-premium-thumb" aria-hidden="true">
                            <div class="history-premium-paper {{ $portrait ? 'is-portrait' : 'is-landscape' }}">
                                <div class="history-premium-placeholder"><span></span><i></i><b></b><em></em></div>
                                @if($batch->history_has_preview)
                                    @if($batch->history_preview_mime === 'application/pdf')
                                        <iframe loading="lazy" tabindex="-1" title="Design preview" src="{{ route('batches.artwork', $batch) }}#toolbar=0&navpanes=0&scrollbar=0&view=Fit"></iframe>
                                    @else
                                        <img src="{{ route('batches.artwork', $batch) }}" alt="" loading="lazy">
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="history-premium-info">
                            <a class="history-premium-title" href="{{ route('batches.show', $batch) }}" aria-label="View batch: {{ $batch->title }}">{{ $batch->title }}</a>
                            <div class="history-premium-meta">
                                <time datetime="{{ $batch->created_at?->toIso8601String() }}">{{ $batch->created_at?->format('M j, Y · g:i A') }}</time>
                                @if($batch->bundled_demo_key)
                                    <span class="history-premium-dot" aria-hidden="true">·</span>
                                    <span title="Included sample batch; you can move it to Trash">Built-in sample</span>
                                @endif
                                <span class="history-premium-dot" aria-hidden="true">·</span>
                                @if($batch->status === 'completed')
                                    {{ number_format($batch->completed_count) }} {{ $batch->completed_count === 1 ? 'certificate' : 'certificates' }}
                                @else
                                    {{ number_format($batch->completed_count) }} of {{ number_format($batch->total_count) }} ready
                                @endif
                            </div>
                            @if($batch->status === 'processing' && $batch->total_count > 0)
                                <div class="history-premium-progress" role="progressbar" aria-label="Batch processing progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ max(0, min(100, $progress)) }}%"></span></div>
                            @endif
                            @if(in_array($batch->status, ['failed', 'partial'], true) && $batch->firstFailure)
                                <details class="history-premium-error"><summary>Why generation failed</summary><p>{{ $batch->firstFailure->error ?: 'Open this batch to review the failure.' }}</p></details>
                            @endif
                        </div>
                        <span class="history-premium-status {{ $statusClass }}">{{ $statusLabel }}</span>
                        @if($batch->status !== 'processing')
                            <details class="history-trash-more">
                                <summary aria-label="Options for {{ $batch->title }}" title="Batch options">•••</summary>
                                <div class="history-trash-menu">
                                    <a href="{{ route('batches.show', $batch) }}">View details</a>
                                    @if($batch->completed_count > 0)<a href="{{ route('batches.download', $batch) }}">Download batch</a>@endif
                                    <form action="{{ route('batches.trash', $batch) }}" method="post" onsubmit="return confirm('Move this entire batch and its certificates to Trash?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="history-trash-danger">Move to Trash</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                        <svg class="history-premium-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                    </article>
                @endforeach
            </section>
            @if($batches->hasPages())
                <nav class="history-premium-pagination" aria-label="History pages">{{ $batches->links() }}</nav>
            @endif
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/certify-history-208.js') }}?v=2.0.8" defer></script>
@endpush
