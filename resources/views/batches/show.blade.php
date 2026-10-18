@extends('layouts.app')
@section('title', 'Batch details')
@section('hide_top', '1')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-trash-274.css') }}?v=2.0.74">
<link rel="stylesheet" href="{{ asset('css/certify-batch-details-275.css') }}?v=2.0.75">
<link rel="stylesheet" href="{{ asset('css/certify-batch-options-276.css') }}?v=2.0.77">
@endpush
@section('content')
@php
    $showsCertificateNumber = \App\Support\CertificateFilename::hasNumberField($batch->template_snapshot ?? []);
    $awardsByNumber = $batch->template_snapshot['awards_by_number'] ?? [];
    $isComplete = $batch->status === 'completed';
    $isFailed = $batch->status === 'failed';
    $hasFailures = $batch->failed_count > 0;
    $batchState = $isComplete ? 'success' : ($isFailed ? 'error' : ($hasFailures ? 'warning' : 'processing'));
    $query = $searchQuery ?? '';
    $selectedStatus = $statusFilter ?? 'all';
    $selectedSort = $sortOrder ?? 'original';
    $width = (float) ($batch->template_snapshot['width_mm'] ?? 0);
    $height = (float) ($batch->template_snapshot['height_mm'] ?? 0);
    $isA4 = (abs(min($width, $height) - 210) < 2 && abs(max($width, $height) - 297) < 2);
    $pageSize = $width > 0 && $height > 0
        ? ($isA4 ? 'A4 ' : '').($width <= $height ? 'Portrait' : 'Landscape').' · '.round($width).' × '.round($height).' mm'
        : null;
    $showStatusFilter = !$isComplete || $selectedStatus !== 'all';
@endphp
<div class="batch-detail-page">
    <header class="batch-detail-pagehead">
        <div class="batch-detail-title-wrap">
            <nav class="batch-detail-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('batches.index') }}">History</a>
                <span aria-hidden="true">/</span><span aria-current="page">{{ $batch->title }}</span>
            </nav>
            <h1>{{ $batch->title }}</h1>
            <p class="batch-detail-subtitle">
                <span>{{ $batch->template?->name ?? 'Original design' }}</span>
                <span class="batch-detail-dot" aria-hidden="true"></span>
                <span>{{ $batch->created_at?->format('M j, Y') ?? 'Date unavailable' }}</span>
                @if($batch->bundled_demo_key)
                    <span class="batch-detail-dot" aria-hidden="true"></span>
                    <span title="Included sample batch; original output is available for preview and download">Built-in sample</span>
                @endif
                @if($pageSize)<span class="batch-detail-dot" aria-hidden="true"></span><span>{{ $pageSize }}</span>@endif
                @if(($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up')<span class="batch-detail-dot" aria-hidden="true"></span><span>2 per A4 sheet · {{ (float) ($batch->template_snapshot['print_margin_mm'] ?? 0) }} mm margin</span>@endif
            </p>
        </div>
        <div class="batch-detail-head-actions">
            <details class="batch-detail-more batch-options" id="batchOptions">
                <summary class="btn light batch-options-trigger" aria-label="More batch options" title="Batch options">
                    <svg class="batch-options-more-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">
                        <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                    </svg>
                </summary>
                <div class="batch-detail-more-panel batch-options-panel">
                    <div class="batch-options-heading">Batch options</div>
                    <a class="batch-options-item" href="{{ route('batches.create') }}">
                        <span class="batch-options-item-icon" aria-hidden="true">＋</span>
                        <span>Create new batch</span>
                    </a>
                    <details class="batch-options-rename" @if($errors->has('title')) open @endif>
                        <summary class="batch-options-item">
                            <span class="batch-options-item-icon" aria-hidden="true">✎</span>
                            <span>Rename batch</span>
                            <svg class="batch-options-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </summary>
                        <form method="post" action="{{ route('batches.rename', $batch) }}" class="batch-options-rename-form">
                            @csrf @method('PUT')
                            <label for="renameBatchTitle" class="sr-only">Batch name</label>
                            <input id="renameBatchTitle" type="text" name="title" maxlength="150" required value="{{ old('title', $batch->title) }}" placeholder="Batch name">
                            @error('title')<div class="batch-options-error">{{ $message }}</div>@enderror
                            <button class="btn secondary" type="submit">Save name</button>
                        </form>
                    </details>
                    @if($batch->status !== 'processing' && $pendingCount === 0)
                        <div class="batch-options-separator" role="separator"></div>
                        <form method="post" action="{{ route('batches.trash', $batch) }}" class="batch-options-trash" onsubmit="return confirm('Move this entire batch and all its certificates to Trash?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="batch-options-item batch-options-danger">
                                <span class="batch-options-item-icon" aria-hidden="true">×</span>
                                <span>Move batch to Trash</span>
                            </button>
                        </form>
                    @endif
                </div>
            </details>
            <a href="{{ route('batches.combined-pdf', $batch) }}" class="btn secondary batch-detail-sheet-download" @if(!$isComplete) aria-disabled="true" tabindex="-1" style="pointer-events:none;opacity:.5" title="Available after all certificates are ready" @else title="{{ ($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up' ? 'One PDF, with two certificates on every A4 sheet' : 'One PDF with every certificate on its own page' }}" @endif>
                <span aria-hidden="true">↓</span> One PDF @if(($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up')<span>(2 per A4)</span>@endif
            </a>
            <a href="{{ route('batches.download', $batch) }}" class="btn batch-detail-download" id="downloadZip" @if($batch->completed_count === 0)style="display:none"@endif title="Download a ZIP containing the individual certificate PDFs">
                <span aria-hidden="true">↓</span> Download ZIP <span id="batchZipCount" class="sr-only">({{ $batch->completed_count }})</span>
            </a>
        </div>
    </header>

    @if($batch->firstFailure && in_array($batch->status, ['failed', 'partial'], true))
        <div class="notice notice-error batch-failure-explanation" role="alert">
            <strong>Why PDF generation failed</strong>
            <p class="small" style="margin:7px 0 4px;color:inherit;overflow-wrap:anywhere">{{ $batch->firstFailure->error ?: 'No detailed error is available for this certificate.' }}</p>
            <p class="small" style="margin:6px 0 0;color:inherit"><strong>Next step:</strong> {{ \App\Support\CertificateGenerationFailure::advice($batch->firstFailure->error) }}</p>
        </div>
    @endif
    @if(!empty($pdfEngineMissing))
        <div class="notice notice-error" role="alert">
            <strong>PDF library missing — saved certificates are safe.</strong>
            <p style="margin:7px 0;color:inherit">Install {{ implode(' and ', $pdfEngineMissing) }} by running <code>composer require setasign/fpdi tecnickcom/tcpdf -W</code>, then <code>php artisan optimize:clear</code>.</p>
        </div>
    @endif

    <section class="panel batch-detail-result batch-detail-result-{{ $batchState }} {{ $isComplete ? 'batch-detail-result-compact' : '' }}" id="batchProcessor"
        data-process-url="{{ $processUrl }}"
        data-retry-url="{{ route('batches.retry', $batch) }}"
        data-auto-recovery="{{ $autoRecovery ? '1' : '0' }}"
        data-pending="{{ $pendingCount }}"
        data-engine-ready="{{ empty($pdfEngineMissing) ? '1' : '0' }}">
        <div class="batch-detail-result-top">
            <span class="batch-detail-result-icon" aria-hidden="true">@if($isComplete)✓@elseif($isFailed)!@else↻@endif</span>
            <div class="batch-detail-result-text">
                <h2 id="batchHeading">{{ $isComplete ? number_format($batch->completed_count).' certificates ready' : ($isFailed ? 'Generation needs attention' : ($hasFailures ? 'Some certificates need attention' : 'Creating your certificates')) }}</h2>
                <p id="batchMessage" @if($isComplete)hidden @endif>{{ $isComplete ? '' : ($batch->status === 'processing' ? 'Creating PDFs. This page will update as they finish.' : 'Review the error and retry when ready.') }}</p>
            </div>
            <span class="batch-detail-completion" id="batchCompletionPercent" aria-label="{{ round($percentage) }} percent processed" @if($isComplete)hidden @endif>{{ round($percentage) }}%</span>
        </div>
        <div class="batch-detail-progress-group" @if($isComplete)hidden @endif>
            <div class="batch-detail-progress-label"><span>Progress</span><span id="batchProcessedTotal">{{ $batch->completed_count + $batch->failed_count }} of {{ $batch->total_count }}</span></div>
            <div class="batch-progress batch-detail-progress" role="progressbar" aria-label="Certificate generation progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($percentage) }}" id="progressBar">
                <div id="progressFill" style="width:{{ $percentage }}%"></div>
            </div>
        </div>
        <div class="batch-detail-metrics" @if($isComplete)hidden @endif aria-label="Batch certificate counts">
            <span><strong id="readyCount">{{ $batch->completed_count }}</strong> ready</span>
            <span><strong id="failedCount">{{ $batch->failed_count }}</strong> failed</span>
            <span><strong id="pendingCount">{{ $pendingCount }}</strong> remaining</span>
        </div>
        <div class="notice notice-error" id="processingError" style="display:none;margin-top:15px"></div>
        <div class="batch-detail-actions" @if($isComplete)hidden @endif>
            @if($pendingCount > 0 && empty($pdfEngineMissing))
                <form method="post" action="{{ route('batches.process', $batch) }}" id="manualGenerationForm">@csrf<button type="submit" class="btn secondary" id="manualGenerate">Generate pending PDFs</button></form>
            @endif
            <button type="button" class="btn secondary" id="resumeButton" style="display:none">Continue automatically</button>
            @if($batch->failed_count > 0 && $pendingCount === 0)
                <form method="post" action="{{ route('batches.retry', $batch) }}">@csrf<button type="submit" class="btn secondary" @disabled(!empty($pdfEngineMissing))>Retry failed PDFs</button></form>
            @endif
        </div>
        <div class="hint" style="margin-top:12px;{{ $batch->status === 'processing' ? '' : 'display:none;' }}" id="processingInfo">Keep this page open while certificates are generated.</div>
        <span class="sr-only" id="batchStatus"><span id="batchStatusLabel">{{ $isComplete ? 'Completed' : ($isFailed ? 'Failed' : 'Processing') }}</span></span>
    </section>

    @if(($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up')
        <p class="batch-detail-print-tip"><span aria-hidden="true">ⓘ</span><span><strong>Print tip:</strong> Use A4 Portrait at Actual Size (100%). {{ ($batch->template_snapshot['print_margin_mm'] ?? 0) == 0 ? 'Full bleed requires a borderless printer.' : 'The layout already includes a safe print margin.' }}</span></p>
    @endif
    <section class="panel batch-detail-recipients">
        <div class="batch-detail-recipient-heading">
            <div class="batch-detail-recipient-title"><h2>Recipients</h2><span class="batch-detail-recipient-total">{{ number_format($certs->total()) }} {{ $certs->total() === 1 ? 'certificate' : 'certificates' }}</span></div>
            @if($batch->completed_count > 0)
                <button type="button" class="batch-detail-select-toggle" id="batchSelectToggle" aria-pressed="false" aria-controls="batchSelectionForm">Select PDFs</button>
            @endif
        </div>
        <form method="get" action="{{ route('batches.show', $batch) }}" class="batch-detail-filter-bar" role="search" id="batchRecipientFilters">
            <div class="batch-detail-search-wrap">
                <label for="recipientSearch" class="sr-only">Search recipient name</label>
                <span class="batch-detail-search-icon" aria-hidden="true">⌕</span>
                <input id="recipientSearch" name="q" type="search" maxlength="120" value="{{ $query }}" placeholder="Search recipients" autocomplete="off">
            </div>
            <label for="recipientStatus" class="sr-only">Filter by certificate status</label>
            <select id="recipientStatus" name="status" aria-label="Filter by certificate status" class="{{ !$showStatusFilter ? 'batch-detail-status-filter-hidden' : '' }}">
                <option value="all" @selected($selectedStatus === 'all')>All statuses</option>
                <option value="ready" @selected($selectedStatus === 'ready')>Ready</option>
                <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                <option value="failed" @selected($selectedStatus === 'failed')>Failed</option>
            </select>
            <label for="recipientSort" class="sr-only">Sort recipients</label>
            <select id="recipientSort" name="sort" aria-label="Sort recipients">
                <option value="original" @selected($selectedSort === 'original')>Original order</option>
                <option value="name_asc" @selected($selectedSort === 'name_asc')>Name A–Z</option>
                <option value="name_desc" @selected($selectedSort === 'name_desc')>Name Z–A</option>
            </select>
            <noscript><button type="submit" class="btn secondary">Apply</button></noscript>
            @if($query !== '' || $selectedStatus !== 'all' || $selectedSort !== 'original')
                <a href="{{ route('batches.show', $batch) }}" class="batch-detail-clear">Clear</a>
            @endif
        </form>
        <form method="post" action="{{ route('batches.download-selected', $batch) }}" id="batchSelectionForm">
            @csrf
            <div class="batch-detail-selection-bar" aria-live="polite">
                <span id="batchSelectedCount">0 selected</span>
                <button type="submit" class="btn secondary" id="downloadSelected" disabled>Download selected</button>
            </div>
            <div class="table-scroll batch-detail-table-wrap">
                <table class="batch-detail-table">
                    <thead><tr>
                        <th scope="col" class="batch-detail-check-col"><input type="checkbox" id="selectAllReady" aria-label="Select all ready certificates on this page" title="Select all ready certificates on this page"></th>
                        <th scope="col">Recipient</th>
                        @if(!$isComplete)<th scope="col">Status</th>@endif
                        <th scope="col" class="batch-detail-action-col">Actions</th>
                    </tr></thead>
                    <tbody>
                        @forelse($certs as $certificate)
                            <tr>
                                <td class="batch-detail-check-col">
                                    @if($certificate->status === 'issued')
                                        <input class="batch-detail-select" type="checkbox" name="certificate_ids[]" value="{{ $certificate->id }}" aria-label="Select certificate for {{ $certificate->recipient_name }}">
                                    @endif
                                </td>
                                <td>
                                    <div class="batch-detail-person-details">
                                        <strong>{{ $certificate->recipient_name }}</strong>
                                        @if($showsCertificateNumber)<span class="batch-detail-person-number">{{ $certificate->certificate_number }}</span>@endif
                                        @if(isset($awardsByNumber[$certificate->certificate_number]))<span class="batch-detail-person-number">{{ $awardsByNumber[$certificate->certificate_number] }}</span>@endif
                                        @if($isComplete && $certificate->status !== 'issued')
                                            <span class="batch-detail-row-error">{{ ucfirst($certificate->status) }}</span>
                                        @endif
                                    </div>
                                </td>
                                @if(!$isComplete)
                                <td>
                                    <span class="batch-detail-row-status batch-detail-row-{{ $certificate->status === 'issued' ? 'ready' : ($certificate->status === 'failed' ? 'failed' : 'pending') }}">{{ $certificate->status === 'issued' ? 'Ready' : ucfirst($certificate->status) }}</span>
                                    @if($certificate->error)<div class="batch-detail-row-error">{{ $certificate->error }}</div>@endif
                                </td>
                                @endif
                                <td class="batch-detail-action-col">
                                    <div class="batch-detail-row-actions">
                                        @if($certificate->status === 'issued')
                                            <button type="button" class="batch-detail-row-preview" data-preview-url="{{ route('certificates.preview', $certificate) }}" data-download-url="{{ route('certificates.download', $certificate) }}" data-recipient="{{ $certificate->recipient_name }}" aria-label="Preview PDF for {{ $certificate->recipient_name }}">Preview</button>
                                            <a href="{{ route('certificates.download', $certificate) }}" class="batch-detail-row-download" aria-label="Download PDF for {{ $certificate->recipient_name }}">Download</a>
                                        @else
                                            <span class="batch-detail-row-unavailable">Not ready</span>
                                        @endif
                                        @if($batch->status !== 'processing' && $pendingCount === 0)
                                            <span class="batch-detail-row-action-separator" aria-hidden="true"></span>
                                            <button type="submit" form="trash-certificate-{{ $certificate->id }}" class="batch-trash-row-button" title="Move certificate to Trash" aria-label="Move certificate for {{ $certificate->recipient_name }} to Trash">Trash</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isComplete ? 3 : 4 }}"><div class="batch-detail-empty"><strong>No recipients found</strong><p>Try another search or filter.</p><a href="{{ route('batches.show', $batch) }}">Clear filters</a></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @if($batch->status !== 'processing' && $pendingCount === 0)
            @foreach($certs as $certificate)
                <form method="post" action="{{ route('certificates.trash', $certificate) }}" id="trash-certificate-{{ $certificate->id }}" class="batch-trash-hidden-form" onsubmit="return confirm('Move the certificate for ' + @js($certificate->recipient_name) + ' to Trash?');">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
        @if($certs->hasPages())
            <div class="batch-detail-pagination">{{ $certs->links() }}</div>
        @elseif($query !== '' || $selectedStatus !== 'all')
            <p class="batch-detail-filter-count">{{ number_format($certs->total()) }} matching recipient{{ $certs->total() === 1 ? '' : 's' }}</p>
        @endif
    </section>
</div>
<div class="batch-pdf-modal" id="batchPdfModal" hidden role="dialog" aria-modal="true" aria-labelledby="batchPdfTitle">
    <div class="batch-pdf-modal-shell">
        <div class="batch-pdf-modal-head">
            <strong id="batchPdfTitle">Certificate preview</strong>
            <div class="batch-pdf-modal-actions">
                <a class="btn secondary" id="batchPdfOpen" target="_blank" rel="noopener noreferrer">Open in browser</a>
                <a class="btn" id="batchPdfDownload">Download</a>
                <button type="button" class="btn light" id="batchPdfClose" aria-label="Close certificate preview">✕</button>
            </div>
        </div>
        <iframe id="batchPdfFrame" title="Generated certificate PDF" referrerpolicy="no-referrer"></iframe>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/certify-batch.js') }}?v=2.0.6" defer></script>
<script src="{{ asset('js/certify-batch-details.js') }}?v=2.0.6" defer></script>
@endpush
