@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'History Trash')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-history-208.css') }}?v=2.0.8">
<link rel="stylesheet" href="{{ asset('css/certify-trash-274.css') }}?v=2.0.74">
<link rel="stylesheet" href="{{ asset('css/certify-trash-278.css') }}?v=2.0.79">
@endpush
@section('content')
<div class="history-premium history-trash-page history-trash-refined">
    <header class="history-premium-header history-trash-page-header">
        <div class="history-premium-intro">
            <h1>Trash</h1>
            <p class="history-premium-subtitle">Restore deleted certificates and batches, or remove them permanently.</p>
        </div>
    </header>

    <nav class="history-trash-tabs" aria-label="History views">
        <a href="{{ route('batches.index') }}">All certificates</a>
        <a class="is-active" aria-current="page" href="{{ route('batches.index', ['view' => 'trash']) }}">Trash @if(($trashedBatchesCount + $trashedCertificatesCount) > 0)<span>{{ $trashedBatchesCount + $trashedCertificatesCount }}</span>@endif</a>
    </nav>

    @if(($trashedBatchesCount + $trashedCertificatesCount) === 0)
        <section class="history-trash-zero" aria-labelledby="trashEmptyTitle">
            <span class="history-trash-zero-icon" aria-hidden="true">
                <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3m3 0-.7 12.3a2 2 0 0 1-2 1.7H8.7a2 2 0 0 1-2-1.7L6 7"/><path d="M10 11v6m4-6v6"/></svg>
            </span>
            <div class="history-trash-zero-text">
                <h2 id="trashEmptyTitle">Trash is empty</h2>
                <p>Deleted certificates and batches will appear here until you restore or permanently delete them.</p>
            </div>
        </section>
    @else
        <p class="history-trash-help history-trash-refined-help">
            <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-9h.01"/></svg>
            Permanently deleted items cannot be recovered. Previously downloaded PDFs are unaffected.
        </p>
        <div class="history-trash-collections">
            @if($trashedBatchesCount > 0)
                <section class="history-trash-section" aria-labelledby="trashBatchesTitle">
                    <div class="history-trash-section-heading">
                        <h2 id="trashBatchesTitle">Deleted batches <span>{{ $trashedBatchesCount }}</span></h2>
                    </div>
                    @foreach($trashedBatches as $batch)
                        <article class="history-trash-entry">
                            <div class="history-trash-entry-info">
                                <strong>{{ $batch->title }}</strong>
                                <small>{{ $batch->total_count }} {{ $batch->total_count === 1 ? 'certificate' : 'certificates' }} <span aria-hidden="true">·</span> Deleted <time datetime="{{ $batch->deleted_at?->toIso8601String() }}">{{ $batch->deleted_at?->format('M j, Y · g:i A') }}</time></small>
                            </div>
                            <div class="history-trash-entry-actions">
                                <form method="post" action="{{ route('batches.restore', $batch->id) }}">@csrf<button type="submit" class="history-trash-action">Restore</button></form>
                                <form method="post" action="{{ route('batches.purge', $batch->id) }}" onsubmit="return confirm('PERMANENTLY delete this batch and all its certificates? This cannot be undone.');">@csrf @method('DELETE')<button type="submit" class="history-trash-action is-danger">Delete permanently</button></form>
                            </div>
                        </article>
                    @endforeach
                    @if($trashedBatches->hasPages())<div class="history-premium-pagination">{{ $trashedBatches->links() }}</div>@endif
                </section>
            @endif

            @if($trashedCertificatesCount > 0)
                <section class="history-trash-section" aria-labelledby="trashCertificatesTitle">
                    <div class="history-trash-section-heading">
                        <h2 id="trashCertificatesTitle">Deleted certificates <span>{{ $trashedCertificatesCount }}</span></h2>
                    </div>
                    @foreach($trashedCertificates as $certificate)
                        <article class="history-trash-entry">
                            <div class="history-trash-entry-info">
                                <strong>{{ $certificate->recipient_name }}</strong>
                                <small>{{ $certificate->batch?->title ?? 'Batch unavailable' }} <span aria-hidden="true">·</span> #{{ $certificate->certificate_number }} <span aria-hidden="true">·</span> Deleted <time datetime="{{ $certificate->deleted_at?->toIso8601String() }}">{{ $certificate->deleted_at?->format('M j, Y · g:i A') }}</time></small>
                                @php $award = $certificate->batch?->template_snapshot['awards_by_number'][$certificate->certificate_number] ?? null; @endphp
                                @if($award)<small>Award: {{ $award }}</small>@endif
                            </div>
                            <div class="history-trash-entry-actions">
                                <form method="post" action="{{ route('certificates.restore', $certificate->id) }}">@csrf<button type="submit" class="history-trash-action">Restore</button></form>
                                <form method="post" action="{{ route('certificates.purge', $certificate->id) }}" onsubmit="return confirm('PERMANENTLY delete the certificate for ' + @js($certificate->recipient_name) + '? This cannot be undone.');">@csrf @method('DELETE')<button type="submit" class="history-trash-action is-danger">Delete permanently</button></form>
                            </div>
                        </article>
                    @endforeach
                    @if($trashedCertificates->hasPages())<div class="history-premium-pagination">{{ $trashedCertificates->links() }}</div>@endif
                </section>
            @endif
        </div>
    @endif
</div>
@endsection
