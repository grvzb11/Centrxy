@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'Font Library')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-font-pagination-248.css') }}?v=2.0.50">
<link rel="stylesheet" href="{{ asset('css/certify-font-variants-250.css') }}?v=2.0.50">
<link rel="stylesheet" href="{{ asset('css/certify-font-manage-refined.css') }}?v=20261017-1">
@endpush
@section('content')
@php
    $customCount = count(array_filter($fonts, fn ($font) => empty($font['built_in']) && empty($font['bundled']) && empty($font['packaged'])));
    $builtInCount = count($fonts) - $customCount;
@endphp

<div class="font-v209">
    <header class="font-v209-header">
        <div>
            <h1>Font Library</h1>
            <p>Built-in PDF and certificate fonts are ready to use offline. Add optional styles only when needed.</p>
        </div>
        <button class="btn font-v209-install-trigger" id="openFontInstall" type="button" aria-haspopup="dialog" aria-controls="fontInstallDialog">+ Install font</button>
    </header>

    <section class="font-v209-preview panel" aria-labelledby="fontPreviewTitle">
        <div class="font-v209-preview-top">
            <div>
                <h2 id="fontPreviewTitle">Preview</h2>
                <p>See how names look in your selected font.</p>
            </div>
            <span class="font-v209-selected-name" id="fontSelectedName">{{ $fonts[0]['name'] ?? 'DejaVu Serif' }}</span>
        </div>
        <div class="font-v209-specimen">
            <div class="font-v209-specimen-overline">CERTIFICATE OF RECOGNITION</div>
            <div class="font-v209-specimen-line" aria-hidden="true"></div>
            <div class="font-v209-specimen-value" id="fontSpecimenName">Juan Dela Cruz</div>
        </div>
        <div class="font-v209-preview-bottom">
            <label for="fontPreviewInput">Sample text</label>
            <input id="fontPreviewInput" type="text" value="Juan Dela Cruz" maxlength="100" autocomplete="off" placeholder="Type a name to preview">
        </div>
    </section>

    <section class="font-v209-library panel" aria-labelledby="fontCollectionTitle">
        <div class="font-v209-library-top">
            <div class="font-v209-library-name">
                <h2 id="fontCollectionTitle">Available fonts</h2>
                <span class="font-v209-count">{{ count($fonts) }}</span>
            </div>
            <div class="font-v209-tools">
                <div class="font-v209-filter" role="group" aria-label="Filter fonts">
                    <button type="button" class="font-filter-btn is-active" data-font-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="font-filter-btn" data-font-filter="builtin" aria-pressed="false">Built-in</button>
                    <button type="button" class="font-filter-btn" data-font-filter="custom" aria-pressed="false">Custom</button>
                </div>
                <div class="font-search-field">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.7" cy="10.7" r="6.5"/><path d="m16 16 5 5"/></svg>
                    <input id="fontSearch" type="search" placeholder="Search fonts" aria-label="Search fonts">
                </div>
            </div>
        </div>
        <div class="font-v209-gallery" id="fontGallery">
            @foreach($fonts as $font)
                @php
                    $builtIn = !empty($font['built_in']) || !empty($font['bundled']) || !empty($font['packaged']);
                    $family = !empty($font['built_in']) ? $font['css'] : ('\'Certrxy'.$font['id'].'\', serif');
                @endphp
                <div class="font-v209-item" data-font-kind="{{ $builtIn ? 'builtin' : 'custom' }}" data-font-name="{{ $font['name'] }}">
                    <button type="button" class="font-v209-choice font-gallery-card" data-font-name="{{ $font['name'] }}" data-font-id="{{ $font['id'] }}" data-font-family="{{ $family }}" aria-pressed="false" aria-label="Preview {{ $font['name'] }}">
                        <span class="font-v209-choice-sample" style="font-family: {{ $family }}">Aa <small>agQ</small></span>
                        <span class="font-v209-choice-title">{{ $font['name'] }}</span>
                        <span class="font-v209-choice-kind">{{ $builtIn ? 'Built-in' : 'Custom' }}</span>
                        <span class="font-v209-choice-check" aria-hidden="true">✓</span>
                    </button>
                    @unless($builtIn)
                        <button type="button" class="font-v209-manage" data-font-manage="{{ $font['id'] }}" data-rename-url="{{ route('fonts.update', ['fontId' => $font['id']]) }}" data-remove-url="{{ route('fonts.destroy', ['fontId' => $font['id']]) }}" data-variant-url="{{ route('fonts.variants.store', ['fontId' => $font['id']]) }}" data-variants="{{ implode(',', array_keys($font['variants'] ?? [])) }}" aria-label="Manage {{ $font['name'] }}" aria-haspopup="dialog" title="Manage this font">···</button>
                    @endunless
                </div>
            @endforeach
        </div>
        <div class="font-v209-empty" id="fontNoResults" hidden>No fonts found. Try another search or filter.</div>
        <nav class="font-v248-pagination" id="fontPagination" aria-label="Font gallery pages" hidden>
            <span class="font-v248-range" id="fontPageRange" role="status" aria-live="polite"></span>
            <div class="font-v248-controls">
                <button type="button" id="fontPagePrevious" class="font-v248-page-nav" aria-label="Previous fonts page">← Previous</button>
                <div class="font-v248-pages" id="fontPageNumbers" aria-label="Page numbers"></div>
                <button type="button" id="fontPageNext" class="font-v248-page-nav" aria-label="Next fonts page">Next →</button>
            </div>
        </nav>
        <p class="font-v209-library-footnote">{{ $builtInCount }} built-in · {{ $customCount }} custom. Select a font to preview it above.</p>
    </section>



</div>

@if($errors->has('font_file') || $errors->has('license_confirmed') || $errors->has('name'))<span data-font-upload-error hidden></span>@endif
<div class="font-v209-overlay" id="fontInstallDialog" role="dialog" aria-modal="true" aria-labelledby="fontInstallTitle" hidden>
    <div class="font-v209-dialog">
        <div class="font-v209-dialog-head"><div><h2 id="fontInstallTitle">Install a font</h2><p>Add a TrueType font to your certificate editor.</p></div><button type="button" class="font-v209-close" data-close-font-dialog aria-label="Close install font dialog">×</button></div>
        <form id="fontInstallForm" action="{{ route('fonts.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <label for="font_name">Font name</label>
            <input id="font_name" name="name" maxlength="80" required autocomplete="off" placeholder="e.g. Great Vibes" value="{{ old('name') }}">
            <label for="font_file">TrueType file (.ttf)</label>
            <div class="font-dropzone" id="fontDropzone">
                <span class="font-v209-upload-symbol" aria-hidden="true">↑</span>
                <strong>Drop a TTF file here</strong>
                <span>or select a file from your computer</span>
                <label for="font_file" class="font-browse-btn">Choose file</label>
                <input id="font_file" class="font-upload-input" type="file" name="font_file" accept=".ttf,font/ttf" required aria-describedby="fontFileHelp">
            </div>
            <p class="font-file-selected" id="fontFileSelected" role="status" aria-live="polite"></p>
            <p id="fontFileHelp" class="font-field-hint">TTF only · Up to 5 MB. Install only fonts you have permission to use.</p>
            <label class="font-license-confirm"><input type="checkbox" name="license_confirmed" value="1" required><span>I have permission to use this font in generated certificates.</span></label>
            <div class="font-v209-dialog-actions"><button type="button" class="btn light" data-close-font-dialog>Cancel</button><button class="btn" type="submit">Install font</button></div>
        </form>
        <p class="font-v209-dialog-hint">Need a font? <a href="https://fonts.google.com/" target="_blank" rel="noopener noreferrer">Browse Google Fonts ↗</a> when you have internet access.</p>
    </div>
</div>

<div class="font-v209-overlay" id="fontManageDialog" role="dialog" aria-modal="true" aria-labelledby="fontManageTitle" hidden>
    <div class="font-v209-dialog font-v209-manage-dialog font-manage-refined">
        <div class="font-v209-dialog-head font-manage-refined-head">
            <div>
                <span class="font-manage-eyebrow">FONT LIBRARY</span>
                <h2 id="fontManageTitle">Manage font</h2>
                <p id="fontManageDescription">Update the font name or install additional styles.</p>
            </div>
            <button type="button" class="font-v209-close" data-close-font-dialog aria-label="Close manage font dialog">×</button>
        </div>

        <section class="font-manage-refined-section font-manage-refined-name" aria-labelledby="fontManageIdentityTitle">
            <h3 id="fontManageIdentityTitle">Font details</h3>
            <form id="fontRenameForm" method="post" action="">
                @csrf
                @method('PATCH')
                <label for="fontRenameInput">Display name</label>
                <div class="font-manage-rename-row">
                    <input id="fontRenameInput" name="name" required maxlength="80" autocomplete="off">
                    <button class="btn" type="submit">Save name</button>
                </div>
            </form>
        </section>

        <section class="font-v250-variants font-manage-refined-section font-manage-refined-styles" aria-labelledby="fontManageStylesTitle">
            <h3 id="fontManageStylesTitle">Font styles</h3>
            <p id="fontVariantsStatus" class="font-manage-style-status" role="status" aria-live="polite">Regular is installed. Add matching TTF files for additional styles.</p>
            <form id="fontVariantForm" method="post" action="" enctype="multipart/form-data">
                @csrf
                <div class="font-manage-fields">
                    <div class="font-manage-field">
                        <label for="fontVariantStyle">Style to add</label>
                        <select id="fontVariantStyle" name="style" required>
                            <option value="B">Bold</option>
                            <option value="I">Italic</option>
                            <option value="BI">Bold Italic</option>
                        </select>
                    </div>
                    <div class="font-manage-field">
                        <label for="fontVariantFile">Matching TTF file</label>
                        <div class="font-manage-file-control">
                            <input id="fontVariantFile" type="file" name="font_file" accept=".ttf,font/ttf" required aria-label="Select a matching TTF font file" aria-describedby="fontVariantFileHint">
                            <span class="font-manage-file-button" aria-hidden="true">Choose file</span>
                            <span id="fontVariantFileName" class="font-manage-file-name" aria-hidden="true">No file selected</span>
                        </div>
                    </div>
                </div>
                <p id="fontVariantFileHint" class="font-manage-helper">Choose the genuine face from the same family. Existing styles cannot be replaced.</p>
                <div class="font-manage-install-bottom">
                    <label class="font-license-confirm" for="fontVariantLicense">
                        <input type="checkbox" id="fontVariantLicense" name="license_confirmed" value="1" required>
                        <span>I have permission to use this font file.</span>
                    </label>
                    <button class="btn" type="submit" id="fontVariantSubmit">Install style</button>
                </div>
            </form>
        </section>

        <div class="font-v209-danger-zone font-manage-refined-danger">
            <div>
                <strong>Remove font</strong>
                <p>Only unused custom fonts can be removed.</p>
            </div>
            <form id="fontRemoveForm" action="" method="post" data-confirm="Remove this unused font? This action cannot be undone.">
                @csrf
                @method('DELETE')
                <button type="submit" class="font-v209-remove-btn">Remove font</button>
            </form>
        </div>
    </div>
</div>

@foreach($fonts as $font)
    @if(empty($font['built_in']))
        <style>@font-face { font-family: 'Certify{{ $font['id'] }}'; src: url('{{ route('fonts.file', $font['id']) }}') format('truetype'); font-weight:400; font-style:normal; font-display: swap; }</style>
    @endif
@endforeach
@endsection
@push('scripts')
<script src="{{ asset('js/certify-fonts.js') }}?v=2.0.50" defer></script>
<script src="{{ asset('js/certify-font-manage-refined.js') }}?v=20261017-1" defer></script>
@endpush
