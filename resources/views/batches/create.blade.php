@extends('layouts.app')
@section('title', 'Generate certificates')
@section('hide_top', '1')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-generate-221.css') }}?v=2.0.49">
<link rel="stylesheet" href="{{ asset('css/certify-awards-252.css') }}?v=2.0.52">
@endpush
@section('content')
@if(!empty($pdfEngineMissing))
    <div class="notice notice-error" role="alert">
        <strong>PDF setup is not finished.</strong>
        <p>Missing components: {{ implode(', ', $pdfEngineMissing) }}.</p>
        <code>composer require setasign/fpdi tecnickcom/tcpdf -W</code>
        <p>Install these in your Certrxy folder and refresh this page before generating.</p>
    </div>
@endif

@if($templates->isEmpty())
    <section class="panel generate-empty">
        <div class="generate-empty-icon" aria-hidden="true">✧</div>
        <h2>First, add a certificate design</h2>
        <p>Upload a PDF or image, place the name field, then come back here to generate a certificate for everyone.</p>
        <a class="btn" href="{{ route('templates.index') }}">Browse templates <span aria-hidden="true">→</span></a>
    </section>
@else
<div class="generate-workspace">
    <section class="generate-main-card" aria-labelledby="generateFormHeading">
        <div class="generate-card-heading">
            <div>
                <h2 id="generateFormHeading">Create a certificate batch</h2>
                <p>Select a template, add recipients, and generate certificates.</p>
            </div>
        </div>

        <form method="post" action="{{ route('batches.store') }}" enctype="multipart/form-data" id="generateForm" novalidate>
            @csrf
            <div class="generate-form-grid">
                <div class="generate-control">
                    <label for="template_id">Certificate design <span class="generate-required" aria-hidden="true">*</span></label>
                    <select name="template_id" id="template_id" required>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('template_id', $selectedTemplateId ?: $templates->first()->id) == $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="generate-control">
                    <label for="title">Batch title <span class="generate-required" aria-hidden="true">*</span> <span class="generate-label-note">(for your records)</span></label>
                    <input id="title" name="title" maxlength="150" required value="{{ old('title') }}" placeholder="e.g. Recognition Day 2026" autocomplete="off">
                </div>
            </div>

            @php
                $selectedHasNumber = $selectedTemplate?->elements()->where('field_key', 'certificate_number')->where('kind', 'text')->exists() ?? false;
                $selectedHasAward = $selectedTemplate?->elements()->where('field_key', 'award_placement')->where('kind', 'text')->exists() ?? false;
            @endphp
            <div id="certificateNumberSettings" class="certificate-number-settings" @if(!$selectedHasNumber) hidden @endif>
            <div class="certificate-numbering-row">
                <div>
                    <label for="certificate_prefix">Certificate number prefix <span class="generate-label-note">(optional)</span></label>
                    <p>Each recipient gets a unique number automatically. Default: <strong>CERT</strong>. Clear the prefix for an ID without letters.</p>
                </div>
                <div class="number-format-controls"><input @disabled(!$selectedHasNumber) id="certificate_prefix" name="certificate_prefix" maxlength="15" pattern="[A-Za-z][A-Za-z0-9-]*" value="{{ old('certificate_prefix', 'CERT') }}" placeholder="CERT" aria-describedby="certificateNumberHelp" autocomplete="off"><label for="certificate_format" class="sr-only">Certificate number style</label><select @disabled(!$selectedHasNumber) id="certificate_format" name="certificate_format"><option value="alphanumeric" @selected(old('certificate_format') !== 'numeric')>Letters + numbers</option><option value="numeric" @selected(old('certificate_format') === 'numeric')>Digits only (after prefix)</option></select></div>
            </div>
            <p class="certificate-number-example" id="certificateNumberHelp">Example: <code id="certificateNumberExample">CERT-7K4M-2Q9P-B6TX</code>. Each recipient gets a different code when the batch is created.</p>

            </div>
            <p id="filenameRule" class="certificate-file-rule" aria-live="polite">{{ $selectedHasNumber ? 'Output filename: Recipient Name - Certificate Number.pdf' : 'Output filename: Recipient Name.pdf' }}</p>
            <fieldset class="generate-print-layout" id="printLayoutOptions">
                <legend>Print layout</legend>
                <div class="generate-layout-options" role="group" aria-label="Print layout">
                    <label class="generate-layout-choice">
                        <input type="radio" name="print_layout" value="single" @checked(old('print_layout', 'single') === 'single') aria-describedby="layoutDescription">
                        <span><strong>Single certificate</strong></span>
                    </label>
                    <label class="generate-layout-choice">
                        <input type="radio" name="print_layout" value="two_up" @checked(old('print_layout') === 'two_up') aria-describedby="layoutDescription">
                        <span><strong>2 per A4 sheet</strong></span>
                    </label>
                </div>
                <p class="generate-layout-description" id="layoutDescription">One certificate per page. Each recipient gets an individual PDF.</p>
                <div class="generate-layout-extra" id="twoUpOptions" hidden>
                    <label class="generate-cut-option"><input id="cutting_guide" type="checkbox" name="cutting_guide" value="1" @checked(old('cutting_guide', true))> Cutting guide</label>
                    <div class="generate-margin-control">
                        <label class="generate-margin-label" for="print_margin_mm">Printer margins</label>
                        <select name="print_margin_mm" id="print_margin_mm" aria-describedby="marginHelp">
                            <option value="3" @selected(old('print_margin_mm', '3') === '3')>3 mm · Print safe</option>
                            <option value="5" @selected(old('print_margin_mm') === '5')>5 mm · Extra safe</option>
                            <option value="0" @selected(old('print_margin_mm') === '0')>0 mm · Full bleed</option>
                        </select>
                    </div>
                    <p id="marginHelp" class="sr-only">Margins protect certificate edges on ordinary printers. Full bleed requires a borderless printer.</p>
                    <p id="sheetPlan" class="sr-only" aria-live="polite">Two landscape certificates on each A4 portrait sheet.</p>
                    <p id="layoutWarning" class="generate-layout-warning" role="alert" hidden>Please select a landscape template for this layout.</p>
                </div>
            </fieldset>
            @error('print_layout')<p class="generate-layout-warning" role="alert">{{ $message }}</p>@enderror
            <div class="generate-section-divider"></div>

            <div class="generate-recipient-header">
                <div>
                    <label for="names">Recipients</label>
                    <p>One name per line, or import a spreadsheet.</p>
                </div>
                <div class="generate-recipient-tools">
                    <span class="generate-count" id="nameCount" role="status" aria-live="polite" aria-atomic="true"><b>0</b> recipients</span>
                    <button class="generate-compact-import" type="button" id="reviewNames" disabled hidden>Review / edit</button>
                    <button class="generate-compact-import generate-import-button" type="button" id="importToggle" aria-expanded="false" aria-controls="nameImportPanel">Import names</button>
                </div>
            </div>
            <textarea name="names" id="names" rows="5" maxlength="180000" spellcheck="false" placeholder="Juan Dela Cruz&#10;Maria Santos&#10;Pedro Reyes" aria-describedby="namesHelp nameValidation">{{ old('names') }}</textarea>
            <div class="generate-names-bottom" id="namesHelp">
                <span id="nameSummary" class="sr-only">One certificate per recipient.</span>
                <span>Maximum {{ number_format(config('certificates.max_import_rows')) }} recipients</span>
            </div>
            <div class="generate-validation" id="nameValidation" role="status" aria-live="polite"></div>
            <p class="generate-privacy-tip">Only import names you're authorized to use. <a href="{{ route('legal.index') }}#privacy" target="_blank" rel="noopener noreferrer">Privacy information ↗</a></p>

            <div class="generate-import-panel" id="nameImportPanel" hidden>
                <label for="csv_file">Import recipient names</label>
                <div class="generate-import-choose">
                    <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,.xlsx,text/plain,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-describedby="importHelp">
                    <button type="button" class="generate-clear-import" id="clearImportedFile" aria-label="Remove selected recipient file" title="Remove selected file" hidden><span aria-hidden="true">&times;</span></button>
                    <span id="filePlaceholder" class="generate-file-placeholder">No file selected</span>
                </div>
                <p id="importHelp">Names from the first column will be added to any pasted names. CSV, TXT, or Excel (.xlsx), maximum 25 MB.</p>
                <p id="filePicked" class="generate-file-picked" role="status" aria-live="polite"></p>
            </div>
            <label class="generate-duplicate-choice" for="remove_duplicates">
                <input type="checkbox" id="remove_duplicates" name="remove_duplicates" value="1" @checked(old('remove_duplicates'))>
                <span>Remove repeated names <small>(optional — keep unchecked when two recipients share a name)</small></span>
            </label>


            <div class="generate-award-inline" id="awardPlacementSection" @if(!$selectedHasAward) hidden @endif>
                <span class="generate-award-inline-icon" aria-hidden="true">★</span>
                <span id="awardAssignmentStatus" class="generate-award-inline-text" aria-live="polite">Placements are configured in the template.</span>
                <a id="awardEditLink" href="{{ route('templates.edit', $selectedTemplate) }}">Edit placements ↗</a>
            </div>
            @error('template_id')<p class="generate-validation is-error" role="alert">{{ $message }}</p>@enderror
            <div class="generate-submit-row">
                <div class="generate-submit-status">
                    <strong id="generateOutputSummary" aria-live="polite">0 recipients · 0 PDFs</strong>
                    <div class="generate-submit-note" id="generationReadiness" aria-live="polite"><span class="generate-status-dot" aria-hidden="true"></span><span>Add a batch title and recipients to continue.</span></div>
                </div>
                <button class="btn generate-primary-action" id="generateButton" type="submit" disabled data-pdf-engine-missing="{{ !empty($pdfEngineMissing) ? '1' : '0' }}">Generate certificates <span aria-hidden="true">→</span></button>
            </div>
        </form>
        <dialog id="recipientReviewDialog" class="generate-confirm" aria-labelledby="recipientReviewTitle">
            <div class="generate-confirm-inner">
                <h2 id="recipientReviewTitle">Review recipient names</h2>
                <p>One name per line. Review spellings and correct imported names before creating PDFs.</p>
                <textarea id="recipientReviewText" rows="13" style="width:100%;padding:12px;border:1px solid #d9e3f2;border-radius:8px;font:inherit;resize:vertical" maxlength="180000" aria-label="Edit recipient list"></textarea>
                <div class="generate-confirm-actions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
                    <button type="button" class="btn secondary" id="recipientReviewCancel">Cancel</button>
                    <button type="button" class="btn" id="recipientReviewApply">Apply names</button>
                </div>
            </div>
        </dialog>
        <dialog id="generateConfirm" class="generate-confirm" aria-labelledby="generateConfirmTitle">
            <div class="generate-confirm-inner">
                <h2 id="generateConfirmTitle">Generate this batch?</h2>
                <p id="generateConfirmDescription">Review the details before creating the PDFs.</p>
                <dl><div><dt>Batch</dt><dd id="confirmTitle"></dd></div><div><dt>Template</dt><dd id="confirmTemplate"></dd></div><div><dt>Recipients</dt><dd id="confirmRecipients"></dd></div><div><dt>Print layout</dt><dd id="confirmLayout"></dd></div></dl>
                <div class="generate-confirm-actions">
                    <button class="btn light" type="button" id="generateConfirmCancel">Cancel</button>
                    <button class="btn" type="button" id="generateConfirmGo">Generate certificates</button>
                </div>
            </div>
        </dialog>
    </section>

    <aside class="generate-preview-card" aria-labelledby="selectedDesignHeading">
        <div class="generate-preview-heading">
            <div>
                <h2 id="selectedDesignHeading">Certificate preview</h2>
                <p id="previewCaption">Original design — add recipients to preview one.</p>
            </div>
            <span class="generate-preview-format" id="previewFormat">{{ $selectedTemplate->mime_type === 'application/pdf' ? 'PDF' : ($selectedTemplate->mime_type === 'image/svg+xml' ? 'SVG' : ($selectedTemplate->mime_type === 'image/png' ? 'PNG' : 'JPG')) }}</span>
        </div>
        <div class="generate-preview-stage" id="templatePreview" aria-label="Selected certificate template preview">
            <div class="generate-preview-paper" id="templatePaper" style="aspect-ratio: {{ max(1, (float)$selectedTemplate->width_mm) }} / {{ max(1, (float)$selectedTemplate->height_mm) }}">
                @if($selectedTemplate->mime_type==='application/pdf')
                    <iframe title="Selected certificate PDF preview" src="{{ route('templates.file', $selectedTemplate) }}#toolbar=0&navpanes=0&scrollbar=0" loading="lazy"></iframe>
                @else
                    <img alt="Selected certificate layout" src="{{ route('templates.file', $selectedTemplate) }}">
                @endif
            </div>
        </div>
        <div class="generate-template-summary">
            <div class="generate-template-name" id="previewName">{{ $selectedTemplate->name }}</div>
            <div class="generate-template-dimensions" id="previewDimensions">{{ round((float)$selectedTemplate->width_mm) }} × {{ round((float)$selectedTemplate->height_mm) }} mm</div>
        </div>
        <div class="generate-proof-tools" aria-label="Certificate proofing">
            <select id="proofRecipientChoice" aria-label="Choose which recipient to preview">
                <option value="first">First recipient</option>
                <option value="longest">Longest name</option>
                <option value="shortest">Shortest name</option>
            </select>
            <button type="button" class="btn secondary" id="checkProof" disabled>Check layout risks</button>
        </div>
        <p id="proofMessage" class="generate-proof-message" aria-live="polite">Use Check layout risks to review potential name-fit issues before generating.</p>
        <div class="generate-preview-controls">
            <button type="button" class="btn generate-preview-sample" id="previewRecipient" disabled>Preview first recipient</button>
            <span id="previewFeedback" class="generate-preview-feedback" role="status" aria-live="polite"></span>
        </div>
        <div class="generate-template-actions">
            <a id="editTemplate" class="btn secondary" href="{{ route('templates.edit', $selectedTemplate) }}">Adjust name placement <span aria-hidden="true">↗</span></a>
            <a id="viewTemplate" class="generate-original-link" href="{{ route('templates.file', $selectedTemplate) }}" target="_blank" rel="noopener" aria-label="Open original template in new tab">View original ↗</a>
        </div>
        <div class="generate-quality-message"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg><span>Names are rendered as sharp PDF text. Background quality depends on the original template.</span></div>
    </aside>
</div>
@endif
@endsection

@push('scripts')
@if($templates->isNotEmpty())
<script id="generateTemplateData" type="application/json">{!! json_encode($templatePreviews, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script id="generateConfig" type="application/json">{!! json_encode(['preview_url' => route('batches.preview-sample'), 'max_rows' => (int) config('certificates.max_import_rows'), 'import_url' => route('batches.import-recipients'), 'proof_url' => route('batches.proof')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/certify-generate.js') }}?v=2.0.52" defer></script>
@endif
@endpush
