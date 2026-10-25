{{-- CERTRXY_DESKTOP_TRANSFER_V2: NativePHP desktop, loopback only; do not deploy publicly. --}}
@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'Templates')
@section('subtitle', 'Your certificate designs, all in one place.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-templates-177.css') }}?v=1.6.17">
<link rel="stylesheet" href="{{ asset('css/certify-templates-178.css') }}?v=1.6.18">
<link rel="stylesheet" href="{{ asset('css/certify-template-transfer.css') }}?v=1">
@endpush
@section('content')
<div class="template-library" id="templateLibrary" data-upload-errors="{{ $errors->any() ? '1' : '0' }}">
    <section class="library-compact-top" aria-label="Template library toolbar">
        <div class="library-top-title">
            <h1>Your templates</h1>
            <p>{{ $activeCount }} active {{ $activeCount === 1 ? 'design' : 'designs' }}<span aria-hidden="true"> · </span>{{ $archivedCount }} archived</p>
        </div>
        <div class="template-library-main-actions">
            @if(in_array(request()->ip(), ['127.0.0.1','::1'], true))
                <button class="btn light" type="button" id="openTemplateImport">↥ Import</button>
            @endif
            <button class="btn library-new-btn" type="button" data-open-template-upload><span aria-hidden="true">＋</span> New template</button>
        </div>
    </section>

    <nav class="library-state-switch" aria-label="Template status">
        <a href="{{ route('templates.index', ['view' => 'active']) }}" class="library-state-link @if($libraryView === 'active') is-active @endif" @if($libraryView === 'active') aria-current="page" @endif>Active <span>{{ $activeCount }}</span></a>
        <a href="{{ route('templates.index', ['view' => 'archived']) }}" class="library-state-link @if($libraryView === 'archived') is-active @endif" @if($libraryView === 'archived') aria-current="page" @endif>Archived <span>{{ $archivedCount }}</span></a>
        <a href="{{ route('templates.index', ['view' => 'all']) }}" class="library-state-link @if($libraryView === 'all') is-active @endif" @if($libraryView === 'all') aria-current="page" @endif>All <span>{{ $activeCount + $archivedCount }}</span></a>
    </nav>
    @if($templates->isNotEmpty() || count($starterTemplates) > 0)
        <section class="library-section" aria-label="Saved certificate designs">
            <div class="library-controls">
                <div class="library-search-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4.5 4.5"/></svg>
                    <label for="librarySearch" class="sr-only">Search templates</label>
                    <input id="librarySearch" type="search" placeholder="Search templates" autocomplete="off" aria-controls="templateGrid">
                </div>
                <div class="library-sort-wrap">
                    <label for="librarySort">Sort</label>
                    <select id="librarySort" class="library-sort" aria-label="Sort templates">
                        <option value="recent">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="name">Name A–Z</option>
                    </select>
                </div>
                <div class="library-filters" role="group" aria-label="Filter by file type">
                    <button type="button" class="library-filter is-active" data-template-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="library-filter" data-template-filter="pdf" aria-pressed="false">PDF</button>
                    <button type="button" class="library-filter" data-template-filter="image" aria-pressed="false">Images</button>
                </div>
            </div>
            <p id="libraryCount" class="library-results" aria-live="polite">Showing {{ $templates->count() + count($starterTemplates) }} {{ $templates->count() + count($starterTemplates) === 1 ? 'template' : 'templates' }}</p>

            <div class="library-grid" id="templateGrid">
                @foreach($templates as $template)
                    @php
                        $isPdf = $template->mime_type === 'application/pdf';
                        $isSvg = $template->mime_type === 'image/svg+xml';
                        $formatLabel = $isPdf ? 'PDF' : ($isSvg ? 'Legacy SVG' : ($template->mime_type === 'image/png' ? 'PNG' : 'JPG'));
                        $orientation = (float) $template->width_mm >= (float) $template->height_mm ? 'Landscape' : 'Portrait';
                        $width = (float) $template->width_mm;
                        $height = (float) $template->height_mm;
                        $sizeLabel = abs(max($width, $height) - 297) < 2 && abs(min($width, $height) - 210) < 2 ? 'A4' : (abs(max($width, $height) - 279.4) < 2 && abs(min($width, $height) - 215.9) < 2 ? 'Letter' : round($width).' × '.round($height).' mm');
                        $fileUrl = route('templates.file', $template);
                    @endphp
                    <article class="library-card @if($template->isArchived()) is-archived @endif" data-template-card data-name="{{ mb_strtolower($template->name) }}" data-type="{{ $isPdf ? 'pdf' : 'image' }}" data-created="{{ $template->created_at?->timestamp ?? 0 }}" data-id="{{ $template->id }}">
                        <div class="library-card-artwork">
                            @if($template->isArchived())<span class="library-archived-badge">Archived</span>@endif
                            <div class="library-card-paper" style="aspect-ratio:{{ max(1, $width) }} / {{ max(1, $height) }}">
                                @if($isPdf)
                                    <iframe loading="lazy" tabindex="-1" title="PDF layout for {{ $template->name }}" src="{{ $fileUrl }}#toolbar=0&navpanes=0&view=Fit"></iframe>
                                @else
                                    <img loading="lazy" alt="Preview of {{ $template->name }}" src="{{ $fileUrl }}">
                                @endif
                            </div>
                            <span class="library-type-tag">{{ $formatLabel }}</span>
                            <button class="library-preview-link" type="button" data-preview-template data-preview-name="{{ $template->name }}" data-preview-url="{{ $fileUrl }}" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-preview-format="{{ $formatLabel }}" data-preview-size="{{ $sizeLabel }} · {{ $orientation }}" aria-label="Preview {{ $template->name }}" title="Preview design">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/></svg>
                            </button>
                        </div>
                        <div class="library-card-content">
                            <div class="library-card-name-row">
                                <h3 title="{{ $template->name }}">{{ $template->name }}</h3>
                                <details class="library-item-menu">
                                    <summary aria-label="More actions for {{ $template->name }}" title="More options">⋯</summary>
                                    <div class="library-item-menu-list">
                                        <button type="button" data-rename-template data-rename-url="{{ route('templates.rename', $template) }}" data-template-name="{{ $template->name }}">Rename</button>
                                        @unless($isSvg)
                                        <form method="post" action="{{ route('templates.duplicate', $template) }}">
                                            @csrf
                                            <button type="submit">Duplicate</button>
                                        </form>
                                        @endunless
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer">View original</a>
                                        @if(in_array(request()->ip(), ['127.0.0.1','::1'], true) && !$isSvg)
                                            <a href="{{ route('templates.export', $template) }}">Export package ↧</a>
                                        @endif
                                        @if($template->isArchived())
                                            <form method="post" action="{{ route('templates.restore', $template) }}">
                                                @csrf
                                                <button type="submit" class="library-menu-restore">Restore</button>
                                            </form>
                                        @else
                                            <button type="button" data-archive-template data-archive-url="{{ route('templates.archive', $template) }}" data-template-name="{{ $template->name }}" data-template-used="{{ $template->batches_count > 0 ? '1' : '0' }}">Archive</button>
                                        @endif
                                        @if($template->batches_count === 0)
                                            <button type="button" class="library-menu-danger" data-delete-template data-delete-url="{{ route('templates.destroy', $template) }}" data-template-name="{{ $template->name }}" data-template-used="0">Delete permanently</button>
                                        @endif
                                    </div>
                                </details>
                            </div>
                            <p class="library-card-meta">{{ $sizeLabel }} · {{ $orientation }} <span class="library-dot" aria-hidden="true"></span> {{ $formatLabel }}</p>
                            <div class="library-card-actions">
                                @if($template->isArchived())
                                    <form class="library-card-restore-form" method="post" action="{{ route('templates.restore', $template) }}">
                                        @csrf
                                        <button class="btn library-restore-btn" type="submit">Restore template <span aria-hidden="true">↺</span></button>
                                    </form>
                                    <button class="btn light" type="button" data-preview-template data-preview-name="{{ $template->name }}" data-preview-url="{{ $fileUrl }}" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-preview-format="{{ $formatLabel }}" data-preview-size="{{ $sizeLabel }} · {{ $orientation }}">Preview <span aria-hidden="true">↗</span></button>
                                @else
                                    <a class="btn library-customize" href="{{ route('templates.edit', $template) }}">Customize <span aria-hidden="true">↗</span></a>
                                    <a class="btn light library-generate" href="{{ route('batches.create', ['template' => $template->id]) }}">Generate <span aria-hidden="true">→</span></a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
                {{-- Bundled designs live in the same grid, never in a second gallery.
                     A stored active template with the same name suppresses its bundle card. --}}
                @foreach($starterTemplates as $key => $starter)
                    @php
                        $isPdf = $starter['mime_type'] === 'application/pdf';
                        $width = (float) $starter['width_mm'];
                        $height = (float) $starter['height_mm'];
                        $orientation = $width >= $height ? 'Landscape' : 'Portrait';
                        $sizeLabel = abs(max($width, $height) - 297) < 2 && abs(min($width, $height) - 210) < 2 ? 'A4' : round($width).' × '.round($height).' mm';
                        $fileUrl = route('templates.starters.preview', ['starter' => $key]);
                    @endphp
                    <article class="library-card certify-bundled-library-card" data-template-card data-name="{{ mb_strtolower($starter['name']) }}" data-type="{{ $isPdf ? 'pdf' : 'image' }}" data-created="0" data-id="0">
                        <div class="library-card-artwork">
                            <div class="library-card-paper" style="aspect-ratio:{{ max(1, $width) }} / {{ max(1, $height) }}">
                                @if($isPdf)
                                    <iframe loading="lazy" tabindex="-1" title="Built-in design {{ $starter['name'] }}" src="{{ $fileUrl }}#toolbar=0&navpanes=0&view=Fit"></iframe>
                                @else
                                    <img loading="lazy" alt="{{ $starter['name'] }} certificate artwork" src="{{ $fileUrl }}">
                                @endif
                            </div>
                            <span class="library-type-tag">Built-in</span>
                            <button class="library-preview-link" type="button" data-preview-template data-preview-name="{{ $starter['name'] }}" data-preview-url="{{ $fileUrl }}" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-preview-format="{{ $isPdf ? 'PDF' : 'Image' }}" data-preview-size="{{ $sizeLabel }} · {{ $orientation }}" aria-label="Preview {{ $starter['name'] }}" title="Preview built-in design">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/></svg>
                            </button>
                        </div>
                        <div class="library-card-content">
                            <div class="library-card-name-row"><h3 title="{{ $starter['name'] }}">{{ $starter['name'] }}</h3></div>
                            <p class="library-card-meta">{{ $sizeLabel }} · {{ $orientation }} <span class="library-dot" aria-hidden="true"></span> {{ $isPdf ? 'PDF' : 'Image' }}</p>
                            <div class="library-card-actions">
                                <form class="certify-bundled-use-form" method="post" action="{{ route('templates.starters.create', ['starter' => $key]) }}">@csrf
                                    <button type="submit" class="btn library-customize">Use design <span aria-hidden="true">→</span></button>
                                </form>
                                <button type="button" class="btn light library-generate" data-preview-template data-preview-name="{{ $starter['name'] }}" data-preview-url="{{ $fileUrl }}" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-preview-format="{{ $isPdf ? 'PDF' : 'Image' }}" data-preview-size="{{ $sizeLabel }} · {{ $orientation }}">Preview <span aria-hidden="true">↗</span></button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="library-no-results" id="libraryNoResults" hidden>
                <div class="library-empty-symbol" aria-hidden="true">⌕</div>
                <h3>No matching designs</h3>
                <p>Try another name or clear the selected filter.</p>
                <button type="button" class="btn light" id="clearTemplateFilters">Clear filters</button>
            </div>
        </section>
    @else
        <section class="library-empty-first" aria-labelledby="emptyTemplatesHeading">
            <div class="library-empty-illustration" aria-hidden="true">
                <svg viewBox="0 0 98 94" width="112" height="108" fill="none"><rect x="11" y="8" width="76" height="77" rx="10" fill="#E8F0FF"/><rect x="20" y="18" width="58" height="56" rx="5" fill="white" stroke="#B7CFFA"/><path d="M33 36h32M33 44h32M40 54h18" stroke="#A6C4F4" stroke-width="3" stroke-linecap="round"/><circle cx="49" cy="28" r="5" fill="#2563EB"/></svg>
            </div>
            @if($libraryView === 'archived')
                <h2 id="emptyTemplatesHeading">No archived templates</h2>
                <p>Designs you archive will appear here, ready to restore whenever you need them.</p>
                <a href="{{ route('templates.index') }}" class="btn light">Back to active templates</a>
            @elseif($libraryView === 'all' && $activeCount === 0)
                <h2 id="emptyTemplatesHeading">No certificate templates yet</h2>
                <p>Upload a PDF, PNG, or JPG to prepare your first reusable design.</p>
                <button type="button" class="btn" data-open-template-upload>＋ Upload your first template</button>
            @else
                <h2 id="emptyTemplatesHeading">{{ $archivedCount > 0 ? 'No active templates' : 'Your first certificate starts here' }}</h2>
                <p>Upload a PDF, PNG, or JPG, then position the recipient name and generate certificates in bulk.</p>
                <button type="button" class="btn" data-open-template-upload>＋ Upload a template</button>
                @if($archivedCount > 0)<a href="{{ route('templates.index', ['view' => 'archived']) }}" class="btn light">View archived designs</a>@endif
            @endif
        </section>
    @endif
</div>

@if(in_array(request()->ip(), ['127.0.0.1','::1'], true))
<dialog class="certify-import-dialog" id="templateImportDialog" aria-labelledby="templateImportTitle">
    <form method="post" action="{{ route('templates.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="certify-import-head"><h2 id="templateImportTitle">Import a template</h2><button type="button" id="closeTemplateImport" aria-label="Close">×</button></div>
        <p>Bring in a template exported from Certrxy, including its artwork and editable fields.</p>
        <label for="templatePackage">Certrxy template package</label>
        <input type="file" name="package" id="templatePackage" accept=".certify-template,.zip,application/zip" required>
        <p class="certify-import-hint">Maximum 25 MB · Does not import recipient data, font files or private signature images. Existing templates are never overwritten.</p>
        <div class="certify-import-actions"><button class="btn light" type="button" id="cancelTemplateImport">Cancel</button><button class="btn" type="submit">Import template</button></div>
    </form>
</dialog>
<script>
(() => {
    const modal = document.getElementById('templateImportDialog');
    if (!modal) return;
    document.getElementById('openTemplateImport')?.addEventListener('click', () => modal.showModal());
    for (const id of ['closeTemplateImport', 'cancelTemplateImport']) {
        document.getElementById(id)?.addEventListener('click', () => modal.close());
    }
    modal.addEventListener('click', e => { if(e.target === modal) modal.close(); });
})();
</script>
@endif

<dialog class="template-upload-dialog" id="templateUploadDialog" aria-labelledby="templateUploadHeading" aria-describedby="templateUploadDescription">
    <div class="template-upload-modal">
        <div class="template-dialog-heading">
            <div>
                <div class="eyebrow">NEW TEMPLATE</div>
                <h2 id="templateUploadHeading">Upload your certificate</h2>
                <p id="templateUploadDescription">Add your design now. You can position the name on the next screen.</p>
            </div>
            <button type="button" class="template-dialog-close" data-close-template-upload aria-label="Close upload window">×</button>
        </div>
        <form action="{{ route('templates.store') }}" method="post" enctype="multipart/form-data" id="templateUploadForm">
            @csrf
            <div class="template-upload-form-row">
                <div>
                    <label for="name">Template name</label>
                    <input id="name" name="name" maxlength="150" required value="{{ old('name') }}" placeholder="e.g. Certificate of Recognition" autofocus>
                </div>
                <div>
                    <label for="page_size">Page size</label>
                    <select name="page_size" id="page_size" required>
                        <option value="A4L" @selected(old('page_size','A4L')==='A4L')>A4 · Landscape</option>
                        <option value="A4P" @selected(old('page_size')==='A4P')>A4 · Portrait</option>
                        <option value="LETTERL" @selected(old('page_size')==='LETTERL')>Letter · Landscape</option>
                        <option value="LETTERP" @selected(old('page_size')==='LETTERP')>Letter · Portrait</option>
                    </select>
                </div>
            </div>
            <label class="template-file-drop" id="templateFileDrop" for="file">
                <span class="template-upload-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m0 0L7 8m5-5 5 5"/><path d="M4 14v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/></svg>
                </span>
                <strong id="templateFileLabel">Choose a file or drag it here</strong>
                <span id="templateFileDescription">PDF, PNG, or JPG · Up to 20 MB</span>
            </label>
            <input class="template-visually-hidden-file" type="file" id="file" name="file" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" required aria-describedby="templateFileDescription">
            <div class="template-quality-note"><span aria-hidden="true">ⓘ</span> For best print quality, use a PDF exported directly from your design application. PNG and JPG are also supported; use high-resolution originals. Match the page size to your design.</div>
            <div class="template-dialog-actions">
                <button type="button" class="btn light" data-close-template-upload>Cancel</button>
                <button type="submit" class="btn" id="templateSubmitButton">Continue to editor <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </div>
</dialog>
<dialog class="template-action-dialog template-preview-dialog" id="templatePreviewDialog" aria-labelledby="templatePreviewTitle">
    <div class="template-action-head">
        <div><h2 id="templatePreviewTitle">Certificate preview</h2><p id="templatePreviewMeta"></p></div>
        <button type="button" class="template-dialog-close" data-close-template-preview aria-label="Close preview">×</button>
    </div>
    <div class="template-preview-stage" id="templatePreviewStage"></div>
    <div class="template-action-foot">
        <span>Original design · No recipient data applied</span>
        <div class="template-action-buttons">
            <a class="btn light" id="templatePreviewOriginal" href="#" target="_blank" rel="noopener noreferrer">Open original ↗</a>
            <button class="btn" type="button" data-close-template-preview>Close</button>
        </div>
    </div>
</dialog>

<dialog class="template-action-dialog template-rename-dialog" id="templateRenameDialog" aria-labelledby="templateRenameTitle">
    <form id="templateRenameForm" method="post">
        @csrf
        @method('PUT')
        <div class="template-action-head"><div><h2 id="templateRenameTitle">Rename template</h2><p>Give this design a clear, recognizable name.</p></div><button type="button" class="template-dialog-close" data-close-template-rename aria-label="Cancel rename">×</button></div>
        <div class="template-action-body">
            <label for="templateRenameInput">Template name</label>
            <input type="text" id="templateRenameInput" name="name" maxlength="150" required autocomplete="off">
        </div>
        <div class="template-action-foot template-action-buttons">
            <button class="btn light" type="button" data-close-template-rename>Cancel</button>
            <button class="btn" type="submit">Save name</button>
        </div>
    </form>
</dialog>

<dialog class="template-action-dialog template-archive-dialog" id="templateArchiveDialog" aria-labelledby="templateArchiveTitle">
    <form id="templateArchiveForm" method="post">
        @csrf
        <div class="template-action-head"><div><h2 id="templateArchiveTitle">Archive template?</h2><p id="templateArchiveName"></p></div><button type="button" class="template-dialog-close" data-close-template-archive aria-label="Cancel archive">×</button></div>
        <div class="template-action-body"><p id="templateArchiveMessage">Archiving hides this design from active templates and new certificate generation. You can restore it at any time.</p></div>
        <div class="template-action-foot template-action-buttons">
            <button class="btn light" type="button" data-close-template-archive>Cancel</button>
            <button class="btn" id="templateArchiveConfirm" type="submit">Archive template</button>
        </div>
    </form>
</dialog>

<dialog class="template-action-dialog template-delete-dialog" id="templateDeleteDialog" aria-labelledby="templateDeleteTitle">
    <form id="templateDeleteForm" method="post">
        @csrf
        @method('DELETE')
        <div class="template-action-head"><div><h2 id="templateDeleteTitle">Permanently delete template?</h2><p id="templateDeleteName"></p></div><button type="button" class="template-dialog-close" data-close-template-delete aria-label="Cancel deletion">×</button></div>
        <div class="template-action-body"><p id="templateDeleteMessage">This removes the unused template and its source file. This cannot be undone.</p></div>
        <div class="template-action-foot template-action-buttons">
            <button class="btn light" type="button" data-close-template-delete>Cancel</button>
            <button class="btn template-danger-btn" id="templateDeleteConfirm" type="submit">Delete permanently</button>
        </div>
    </form>
</dialog>
<script src="{{ asset('js/certify-templates.js') }}?v=1.6.19" defer></script>
@endsection
