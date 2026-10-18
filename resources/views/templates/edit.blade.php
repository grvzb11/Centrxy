@extends('layouts.app')
@section('title', 'Customize template')
@section('subtitle'){{ $template->name }} · Position and style your certificate fields.
@endsection
@section('topaction')<a class="btn light" href="{{ route('templates.index') }}">← Back to templates</a>@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-editor-165.css') }}?v=1.6.5">
<link rel="stylesheet" href="{{ asset('css/certify-editor-166.css') }}?v=1.6.6">
<link rel="stylesheet" href="{{ asset('css/certify-editor-167.css') }}?v=1.6.7">
<link rel="stylesheet" href="{{ asset('css/certify-editor-168.css') }}?v=1.6.8">
<link rel="stylesheet" href="{{ asset('css/certify-editor-169.css') }}?v=1.6.9">
<link rel="stylesheet" href="{{ asset('css/certify-editor-170.css') }}?v=1.6.10">
<link rel="stylesheet" href="{{ asset('css/certify-editor-171.css') }}?v=1.6.11">
<link rel="stylesheet" href="{{ asset('css/certify-editor-172.css') }}?v=1.6.12">
<link rel="stylesheet" href="{{ asset('css/certify-editor-173.css') }}?v=1.6.13">
<link rel="stylesheet" href="{{ asset('css/certify-editor-174.css') }}?v=2.0.30">
<link rel="stylesheet" href="{{ asset('css/certify-award-editor-253.css') }}?v=2.0.55">
<link rel="stylesheet" href="{{ asset('css/certify-multiselect-256.css') }}?v=2.0.56">
<link rel="stylesheet" href="{{ asset('css/certify-group-resize-271.css') }}?v=2.0.72">
<link rel="stylesheet" href="{{ asset('css/certify-alignment-guides-258.css') }}?v=2.0.58">
<link rel="stylesheet" href="{{ asset('css/certify-editor-refinement-259.css') }}?v=2.0.59">
<link rel="stylesheet" href="{{ asset('css/certify-signatures-260.css') }}?v=2.0.60">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-fields-262.css') }}?v=2.0.63">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-block-263.css') }}?v=2.0.63">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-layout-264.css') }}?v=2.0.66">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-compact-267.css') }}?v=2.0.67">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-style-tab-268.css') }}?v=2.0.68">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-polish-269.css') }}?v=2.0.69">
<link rel="stylesheet" href="{{ asset('css/certify-signatory-text-style-270.css') }}?v=2.0.70">
<link rel="stylesheet" href="{{ asset('css/certify-inline-fields-281.css') }}?v=2.0.81">
<link rel="stylesheet" href="{{ asset('css/certify-inline-clarity-282.css') }}?v=2.0.82">
<link rel="stylesheet" href="{{ asset('css/certify-editor-save-discard.css') }}?v=20261017-1">
<link rel="stylesheet" href="{{ asset('css/certify-fontpicker-word-v3.css') }}?v=20261017-5">
<link rel="stylesheet" href="{{ asset('css/certify-editor-draft.css') }}?v=20261017-1">
@endpush
@section('content')
<div class="certify-editor-page">
    <div class="editor-layout">
        <section class="editor-workspace panel" aria-label="Certificate editing canvas">
            <div class="canvas-toolbar">
                <div class="canvas-heading">
                    <div class="canvas-symbol" aria-hidden="true">▤</div>
                    <div><h2 class="toolbar-label">Certificate canvas</h2><p class="small">Drag fields to move. Ctrl / Shift + click to select multiple.</p></div>
                </div>
                <div class="editor-canvas-actions">
                    <span class="editor-canvas-badge">{{ round((float)$template->width_mm) }} × {{ round((float)$template->height_mm) }} mm</span>
                    <a class="editor-back-link" href="{{ route('templates.index') }}">← Templates</a>
                </div>
            </div>
            <div class="editor-preview-controls">
                <div class="name-test editor-control-unit">
                    <label for="sampleName">Preview recipient</label>
                    <div class="editor-control-inline">
                        <input type="text" id="sampleName" maxlength="180" value="Juan Dela Cruz" placeholder="Type a sample name">
                        <button type="button" id="longName" class="btn light small-btn">Long name test</button>
                        <div class="editor-view-tools editor-preview-view" role="group" aria-label="Preview display options">
                            <label for="canvasZoom" class="editor-visually-hidden">Preview zoom</label>
                            <select id="canvasZoom" aria-label="Certificate preview zoom" title="Preview zoom">
                                <option value="fit">Fit page</option>
                                <option value="1">100%</option>
                                <option value="1.25">125%</option>
                                <option value="1.5">150%</option>
                            </select>
                            <button class="editor-tool-button editor-view-fullscreen" type="button" id="toggleCanvasFullscreen" aria-label="Expand certificate canvas" title="Expand certificate canvas">⛶</button>
                        </div>
                    </div>
                </div>
                <div class="editor-alignment editor-control-unit" role="group" aria-label="Center selected field">
                    <span class="editor-toolbar-caption">Position field</span>
                    <div class="editor-tool-buttons">
                        <button class="editor-tool-button" type="button" id="editorUndo" title="Undo (Ctrl+Z)" aria-label="Undo edit" disabled>↶ <span>Undo</span></button>
                        <button class="editor-tool-button" type="button" id="editorRedo" title="Redo (Ctrl+Y)" aria-label="Redo edit" disabled>↷ <span>Redo</span></button>
                        <button class="editor-tool-button" type="button" id="centerX" title="Center selected text box horizontally" aria-label="Center field horizontally">↔ <span>Horizontal</span></button>
                        <button class="editor-tool-button" type="button" id="centerY" title="Center selected text box vertically" aria-label="Center field vertically">↕ <span>Vertical</span></button>
                    </div>
                </div>

            </div>
            <div class="editor-selection-toolbar" role="group" aria-label="Field selection and canvas display">
                <div class="editor-selection-context">
                    <span class="editor-selection-count" id="editorSelectionCount" aria-live="polite">1 field selected</span>
                    <div class="editor-selection-actions">
                        <button type="button" id="editorSelectAll" class="editor-selection-btn">Select all</button>
                        <button type="button" id="editorClearSelection" class="editor-selection-btn">Clear</button>
                        <button type="button" id="editorGroupSelection" class="editor-selection-btn is-emphasized" disabled>Group</button>
                        <button type="button" id="editorUngroupSelection" class="editor-selection-btn" disabled>Ungroup</button>
                        <span class="editor-selection-separator" aria-hidden="true"></span>
                        <button type="button" id="editorCopySelection" class="editor-selection-btn" title="Copy selected fields (Ctrl+C)">Copy</button>
                        <button type="button" id="editorPasteSelection" class="editor-selection-btn" title="Paste duplicated fields (Ctrl+V)" disabled>Paste</button>
                    </div>
                </div>
                <div class="editor-mode-toggle" role="group" aria-label="Canvas display mode">
                    <button type="button" id="editorEditMode" class="editor-mode-option" aria-pressed="true">Edit</button>
                    <button type="button" id="editorCleanPreview" class="editor-mode-option" aria-pressed="false">Preview</button>
                </div>
            </div>
            <div class="editor-stage" id="editorStage">
                <div class="preview{{ $template->mime_type === 'application/pdf' ? ' is-pdf-template' : '' }}" id="canvas" style="aspect-ratio:{{ (float)$template->width_mm }} / {{ (float)$template->height_mm }};--max-preview-w:{{ round(min(950,max(270,((float)$template->width_mm/max(1,(float)$template->height_mm))*520))) }}px">
                    @if($template->mime_type === 'application/pdf')
                        <iframe src="{{ route('templates.file', $template) }}#toolbar=0&navpanes=0&scrollbar=0&view=Fit&zoom=page-fit" title="Original PDF certificate layout"></iframe>
                    @else
                        <img src="{{ route('templates.file', $template) }}" alt="Original certificate layout">
                    @endif
                </div>
            </div>
            <div class="editor-canvas-footer">
                <span><span class="editor-hint-mark">i</span> <span id="editorSelectionTip">Ctrl / Shift + click to select more fields. Group to keep them together.</span> Changes save when you select Save.</span>
            </div>
        </section>

        <aside class="editor-inspector" aria-label="Certificate editing controls">
            <section class="panel editor-properties-card">
                <div class="editor-main-tabs" role="tablist" aria-label="Certificate editor settings">
                    <button id="editorTabFields" type="button" role="tab" data-editor-tab="fields" aria-controls="editorPanelFields" aria-selected="true" tabindex="0">Certificate Fields</button>
                    <button id="editorTabStyle" type="button" role="tab" data-editor-tab="style" aria-controls="editorPanelStyle" aria-selected="false" tabindex="-1">Text Style</button>
                    <button id="editorTabPosition" type="button" role="tab" data-editor-tab="position" aria-controls="editorPanelPosition" aria-selected="false" tabindex="-1">Position &amp; Size</button>
                </div>
                <div class="inspector-scroll-body">
                    <section class="certificate-fields-panel editor-tab-panel" id="editorPanelFields" data-editor-panel="fields" role="tabpanel" aria-labelledby="editorTabFields">
                        <div class="editor-panel-heading editor-fields-heading">
                            <span class="editor-panel-icon" aria-hidden="true">▤</span>
                            <div><h2>Fields on certificate</h2><p>Select a field, or add a new one below.</p></div>
                        </div>
                        <div class="editor-fields-toolbar">
                            <span id="fieldCount" role="status" aria-live="polite">Certificate fields</span>
                            <span class="editor-fields-toolbar-help">On this template</span>
                        </div>
                        <div class="editor-field-list" id="fieldList" role="group" aria-label="Fields placed on this certificate" tabindex="0"></div>
                        <div class="editor-add-field-wrapper">
                            <details id="addFieldMenu" class="editor-add-menu">
                                <summary aria-label="Add a certificate field"><span class="editor-add-trigger-label">＋ Add field</span><svg class="editor-add-chevron" aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                                <div class="editor-add-menu-content" role="group" aria-label="Type of field to add">
                                    <button type="button" data-add-field="recipient_name"><b>Recipient name</b><small>Personalized name</small></button>
                                    <button type="button" data-add-field="award_placement"><b>Award / placement</b><small>Personalized title for each winner</small></button>
                                    <button type="button" data-add-field="issued_date"><b>Current date</b><small>Date printed on certificate</small></button>
                                    <button type="button" data-add-field="certificate_number"><b>Certificate number</b><small>Unique reference number</small></button>
                                    <button type="button" data-add-field="static_text"><b>Fixed text / paragraph</b><small>Multiple lines supported</small></button>
                                    <button type="button" data-add-field="e_signature"><b>Signatory block</b><small>Signature image + movable name, designation and line block</small></button>
                                </div>
                            </details>
                        </div>
                        <div id="literalWrap" class="editor-fixed-text-editor" hidden>
                            <label for="literal" id="editorLiteralLabel">Certificate paragraph</label>
                            <textarea id="literal" rows="4" maxlength="2000" placeholder="Write the certificate message here…" spellcheck="true" aria-describedby="literalHelp literalCount"></textarea>
                            <div class="editor-inline-tools" id="editorInlineTools" hidden>
                                <div class="editor-inline-heading"><span class="editor-inline-tools-label">Insert into paragraph</span><span class="editor-inline-instruction">Place cursor first</span></div>
                                <div class="editor-inline-buttons" role="group" aria-label="Insert inline certificate value">
                                    <button type="button" data-inline-token="AWARD" title="Insert the award assigned to each recipient">Award</button>
                                    <button type="button" data-inline-token="DATE" title="Insert the certificate date">Date</button>
                                    <button type="button" data-inline-token="RECIPIENT" title="Insert the recipient's name">Name</button>
                                    <button type="button" data-inline-token="CERTIFICATE_NUMBER" title="Insert certificate reference number">Number</button>
                                </div>
                                <p class="editor-inline-explanation">Inserted values change automatically on each certificate.</p>
                                <button type="button" id="editorConfigureInlineAwards" class="editor-inline-configure" hidden>Set awards and winner counts <span aria-hidden="true">→</span></button>
                                <div class="editor-inline-format">
                                    <label class="editor-inline-style-toggle"><input type="checkbox" id="editorInlineEmphasis"> <span>Highlight inserted values</span></label>
                                    <div id="editorInlineColorRow" class="editor-inline-color-row" hidden><label for="editorInlineColor">Color</label><input type="color" id="editorInlineColor" value="#192A4B"></div>
                                </div>
                            </div>
                            <div class="editor-fixed-text-meta"><span id="literalHelp">Text wraps to fit the paragraph box.</span><span id="literalCount" aria-live="polite">0 / 2000</span></div>
                        </div>
                        <section id="editorAwardSettings" class="editor-award-settings" aria-label="Award placement settings" hidden>
                            <div class="editor-award-settings-head">
                                <h3>Assign awards</h3>
                                <p>Recipients receive awards in the same order as the names entered on Generate.</p>
                            </div>
                            <div class="editor-award-switch" role="radiogroup" aria-label="How are awards assigned?">
                                <label><input type="radio" name="editor_award_mode" value="grouped" checked><span>By group</span></label>
                                <label><input type="radio" name="editor_award_mode" value="manual"><span>One by one</span></label>
                            </div>
                            <p class="editor-award-mode-hint" id="editorAwardModeHint">Assign the same placement to multiple winners.</p>
                            <div id="editorAwardGroupsPanel">
                                <div class="editor-award-column-labels" aria-hidden="true"><span>Award title</span><span>People</span></div>
                                <div id="editorAwardGroupRows" class="editor-award-group-rows"></div>
                                <button type="button" id="editorAddAwardGroup" class="editor-add-award"><span aria-hidden="true">＋</span> Add award</button>
                            </div>
                            <div id="editorAwardManualPanel" hidden>
                                <div class="editor-award-manual-top"><label for="editorAwardManual">One award per recipient</label></div>
                                <textarea id="editorAwardManual" rows="5" maxlength="110000" placeholder="Type the first award here…" spellcheck="false" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" aria-describedby="editorAwardManualHelp editorAwardLineCount"></textarea>
                                <div class="editor-award-manual-footer">
                                    <p class="editor-award-hint" id="editorAwardManualHelp">Line 1 = recipient 1, line 2 = recipient 2, etc.</p>
                                    <span id="editorAwardLineCount" aria-live="polite">0 entries</span>
                                </div>
                            </div>
                            <label class="editor-inline-award-source"><input type="checkbox" id="editorAwardInlineOnly"><span><strong>Show award in paragraph only</strong><small>Hide the separate Award box on the certificate.</small></span></label>
                            <div class="editor-award-summary-line" role="status" aria-live="polite">
                                <span class="editor-award-count" id="editorAwardCount">0</span>
                                <div class="editor-award-summary-copy"><strong id="editorAwardSummary">No certificates planned</strong><span id="editorAwardHint">Add an award to continue.</span></div>
                            </div>
                            <details class="editor-award-preview" id="editorAwardPreview">
                                <summary>See who receives each award <span id="editorAwardPreviewCount" aria-hidden="true"></span></summary>
                                <ol id="editorAwardPreviewList" aria-label="First award assignments"></ol>
                                <p class="editor-award-preview-more" id="editorAwardPreviewMore" hidden></p>
                            </details>
                        </section>
                        <section id="editorSignatureSettings" class="editor-signature-settings" hidden aria-label="E-Signature settings">
                            <div class="editor-signature-heading"><h3>E-Signature</h3><p>Signature images are stored privately and printed on every certificate using this template.</p></div>
                            <div id="editorSignatureExisting" class="editor-signature-existing" hidden>
                                <div class="editor-signature-thumbnail"><img id="editorSignatureThumbnail" alt="Selected signature" /></div>
                                <span>Signature ready</span><button type="button" id="editorSignatureRemove">Remove</button>
                            </div>
                            <div class="editor-signature-switch" role="group" aria-label="Signature input method">
                                <button type="button" id="editorSignatureUploadTab" aria-pressed="true">Upload image</button>
                                <button type="button" id="editorSignatureDrawTab" aria-pressed="false">Draw signature</button>
                            </div>
                            <div id="editorSignatureUploadPanel">
                                <label for="editorSignatureFile">Signature image</label>
                                <input type="file" id="editorSignatureFile" accept=".png,.webp,image/png,image/webp">
                                <p class="editor-signature-tip">Transparent PNG recommended · PNG/WebP · Max 2 MB.</p>
                                <button type="button" id="editorSignatureUploadButton" class="editor-signature-secondary">Upload signature</button>
                            </div>
                            <div id="editorSignatureDrawPanel" hidden>
                                <label>Draw signature with your mouse, stylus, or finger</label>
                                <canvas id="editorSignaturePad" width="720" height="220" aria-label="Signature drawing pad"></canvas>
                                <div class="editor-signature-draw-actions"><button type="button" id="editorSignatureClearPad">Clear drawing</button><button type="button" class="editor-signature-secondary" id="editorSignatureUsePad">Use drawing</button></div>
                            </div>
                            <label class="editor-signature-consent"><input type="checkbox" id="editorSignatureAuthorized"> I confirm I have permission to use this signature on these certificates.</label>
                            <p id="editorSignatureStatus" class="editor-signature-status" role="status" aria-live="polite"></p>
                            <div class="editor-signature-metadata" id="editorSignatureLegacyFields">
                                <label for="editorSignatoryName">Signatory name <span>(optional)</span></label>
                                <input type="text" id="editorSignatoryName" maxlength="120" placeholder="e.g. Maria Santos">
                                <label for="editorSignatoryTitle">Designation <span>(optional)</span></label>
                                <input type="text" id="editorSignatoryTitle" maxlength="120" placeholder="e.g. Event Chairperson">
                                <label class="editor-signature-line"><input type="checkbox" id="editorSignatureLine" checked> Show signature line</label>
                            </div>
                            <div id="editorSignatureSplitAction" class="editor-signature-split" hidden>
                                <p class="editor-signature-tip">Convert this older field into two movable parts: signature image and signatory details.</p>
                                <button type="button" id="editorSignatureSplitButton" class="editor-signature-secondary">Separate image and details</button>
                            </div>
                            <p id="editorSignatureImageHint" class="editor-signature-tip" hidden>The signature image moves independently. Select <b>Signatory details</b> to edit the name, designation, signature line and their fonts. The <b>Draw</b> tab lets you sign directly in Certrxy.</p>
                        </section>
                        <div id="editorSignatoryCombine" class="editor-signatory-combine" hidden>
                            <p>Older signatory name, designation and line fields can be combined into one movable block.</p>
                            <button type="button" id="editorSignatoryCombineButton" class="editor-signature-secondary">Combine existing signatory details</button>
                        </div>
                        <section id="editorSignatoryDetailsSettings" class="editor-signatory-details-settings" hidden aria-labelledby="editorSignatoryDetailsHeading">
                            <div class="editor-signatory-heading">
                                <h3 id="editorSignatoryDetailsHeading">Signatory details</h3>
                                <p>The name, title and line move together; edit their font and styles in Text Style.</p>
                            </div>
                            <div class="editor-signatory-identity-row">
                                <label class="editor-signatory-unified-field" for="editorDetailsName">
                                    Signatory name
                                    <input id="editorDetailsName" type="text" maxlength="120" placeholder="e.g. Juan Dela Cruz" autocomplete="off">
                                </label>
                                <label class="editor-signatory-unified-field" for="editorDetailsTitle">
                                    Designation / position
                                    <input id="editorDetailsTitle" type="text" maxlength="120" placeholder="e.g. Director" autocomplete="off">
                                </label>
                            </div>
                            <button type="button" id="editorDetailsOpenStyle" class="editor-signatory-style-link">Edit font, color &amp; styles <span aria-hidden="true">→</span></button>
                            <label class="editor-signatory-line-setting" for="editorDetailsLine">
                                <span class="editor-signatory-line-copy">
                                    <strong>Signature line</strong>
                                    <small>Show a line above the name</small>
                                </span>
                                <input id="editorDetailsLine" type="checkbox" role="switch">
                                <span class="editor-signatory-line-switch" aria-hidden="true"></span>
                            </label>
                        </section>
                        <section id="editorSignatureRuleSettings" class="editor-signature-rule-settings" hidden>
                            <h3>Signature line</h3>
                            <p>Move or resize this line independently. It will appear in the exported PDF.</p>
                            <label for="editorSignatureRuleColor">Line color</label>
                            <input type="color" id="editorSignatureRuleColor" value="#8695a9">
                        </section>
                        <select id="selection" class="editor-visually-hidden" tabindex="-1" aria-hidden="true"></select>
                    </section>
                    <section class="editor-typography-panel editor-tab-panel" id="editorPanelStyle" data-editor-panel="style" role="tabpanel" aria-labelledby="editorTabStyle" hidden>
                        <div class="editor-tab-heading">
                            <div><h2>Text appearance</h2><p>Customize the currently selected field.</p></div>
                            <span class="editor-selected-name" id="elementTitle">Recipient name</span>
                        </div>
                        <section id="editorSignatoryAppearance" class="editor-signatory-details-settings editor-signatory-appearance-settings" aria-label="Signatory text appearance" hidden>
                            <div class="editor-signatory-shared-heading">Shared appearance</div>
                            <div class="editor-signatory-unified-grid">
                                <label for="editorDetailsFont">Font
                                    <select id="editorDetailsFont" aria-describedby="editorDetailsSharedNote"></select>
                                </label>
                                <label for="editorDetailsColor">Color
                                    <input id="editorDetailsColor" type="color" value="#213854" aria-describedby="editorDetailsSharedNote" title="Color for name and designation">
                                </label>
                            </div>
                            <p id="editorDetailsSharedNote" class="editor-signatory-shared-note" aria-live="polite" hidden></p>
                            <div class="editor-signatory-size-column-heading" aria-hidden="true">
                                <span></span><span>Size (pt)</span><span></span>
                            </div>
                            <div class="editor-signatory-unified-style-row" role="group" aria-label="Signatory name style">
                                <label class="editor-signatory-style-title" for="editorDetailsNameSize">Name</label>
                                <input id="editorDetailsNameSize" type="number" min="6" max="110" step="1" aria-label="Signatory name font size in points">
                                <div class="editor-signatory-style-toggles">
                                    <button type="button" id="editorDetailsNameBold" aria-pressed="false">Bold</button>
                                    <button type="button" id="editorDetailsNameItalic" aria-pressed="false">Italic</button>
                                </div>
                            </div>
                            <div class="editor-signatory-unified-style-row" role="group" aria-label="Designation style">
                                <label class="editor-signatory-style-title" for="editorDetailsTitleSize">Designation</label>
                                <input id="editorDetailsTitleSize" type="number" min="6" max="110" step="1" aria-label="Designation font size in points">
                                <div class="editor-signatory-style-toggles">
                                    <button type="button" id="editorDetailsTitleBold" aria-pressed="false">Bold</button>
                                    <button type="button" id="editorDetailsTitleItalic" aria-pressed="false">Italic</button>
                                </div>
                            </div>
                            <p id="editorDetailsStyleNote" class="editor-signatory-shared-note" role="status" aria-live="polite" hidden></p>
                        </section>
                        <div id="editorStandardTextStyle">
                                <label for="fontFamily">Font family</label>
                                <select id="fontFamily">
                                    @foreach($fonts as $font)
                                        <option value="{{ $font['id'] }}">{{ $font['name'] }}</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('fonts.index') }}" class="font-inline-link">＋ Install a font ↗</a>
                                <div class="two-cols editor-style-data">
                                    <div><label for="fontSize">Font size (pt)</label><input type="number" id="fontSize" min="6" max="110" step="1"></div>
                                    <div class="editor-color-setting"><span class="editor-color-label" id="colorLabel">Text color</span><label class="editor-color-field" for="color" title="Choose text color"><input type="color" id="color" aria-labelledby="colorLabel" title="Choose text color"><span id="colorHex" aria-hidden="true">#192A4B</span></label></div>
                                </div>
                                <div class="editor-style-options">
                                    <div>
                                        <label id="alignmentLabel" for="align">Text alignment</label>
                                        <select id="align" class="editor-original-align" aria-labelledby="alignmentLabel"><option value="L">Left</option><option value="C">Center</option><option value="R">Right</option></select>
                                        <div class="editor-text-align-buttons" role="group" aria-label="Text alignment">
                                            <button type="button" data-align="L" aria-pressed="false" aria-label="Align left" title="Align left"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M4 10h10M4 14h16M4 18h10"/></svg><span>Left</span></button>
                                            <button type="button" data-align="C" aria-pressed="true" aria-label="Align center" title="Align center"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 10h10M4 14h16M7 18h10"/></svg><span>Center</span></button>
                                            <button type="button" data-align="R" aria-pressed="false" aria-label="Align right" title="Align right"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M10 10h10M4 14h16M10 18h10"/></svg><span>Right</span></button>
                                        </div>
                                    </div>
                                    <div>
                                        <label>Font style</label>
                                        <div class="editor-font-appearance">
                                            <input type="checkbox" id="bold" hidden tabindex="-1" aria-hidden="true">
                                            <input type="checkbox" id="italic" hidden tabindex="-1" aria-hidden="true">
                                            <button type="button" id="boldToggle" class="editor-format-button" aria-pressed="false">Bold</button>
                                            <button type="button" id="italicToggle" class="editor-format-button" aria-pressed="false">Italic</button>
                                        </div>
                                    </div>
                                </div>
                                <p id="customFontStyleTip" class="editor-font-style-note" hidden>Install the corresponding TTF font variants from Fonts → Manage font to enable these styles.</p>
                                <label class="switch-label editor-auto-fit"><span><strong>Auto-fit text</strong><small>Reduce the font size when a name is too long.</small></span><input type="checkbox" id="autoFit" checked aria-label="Automatically fit long names"></label>
                        </div>
                    </section>
                    <section class="editor-tab-panel editor-position-panel" id="editorPanelPosition" data-editor-panel="position" role="tabpanel" aria-labelledby="editorTabPosition" hidden>
                        <div class="editor-tab-heading">
                            <div><h2>Position &amp; size</h2><p>Fine-tune your selected field in millimeters.</p></div>
                        </div>
                                <p class="editor-panel-description">Drag or resize the selected field on the canvas, or enter precise measurements in millimeters.</p>
                                <div class="two-cols">
                                    <div><label for="x">X position (mm)</label><input id="x" type="number" min="0" step="0.1"></div>
                                    <div><label for="y">Y position (mm)</label><input id="y" type="number" min="0" step="0.1"></div>
                                    <div><label for="w">Width (mm)</label><input id="w" type="number" min="1" step="0.1"></div>
                                    <div><label for="h">Height (mm)</label><input id="h" type="number" min="1" step="0.1"></div>
                                </div>
                                <div class="editor-position-tip">Tip: Use the horizontal/vertical positioning buttons above the canvas to center a field.</div>
                    </section>
                </div>
                <form method="post" action="{{ route('templates.save',$template) }}" id="saveForm">
                    @csrf @method('PUT')
                    <div id="hiddenFields"></div>
                    <div class="inspector-footer">
                        <div class="editor-change-row">
                            <span class="editor-saving-status" id="saveState" role="status" aria-live="polite">All changes saved</span>
                            <button type="button" id="discardEditorChanges" class="editor-discard-changes" disabled>Discard changes</button>
                        </div>
                        <div class="editor-save-buttons">
                            <button class="btn light" type="submit">Save changes</button>
                            <button class="btn" name="continue" value="1" type="submit">Save &amp; generate →</button>
                        </div>
                    </div>
                </form>
            </section>
        </aside>
    </div>
</div>
@foreach($fonts as $font)
    @if(empty($font['built_in']))
        <style>@font-face { font-family: 'Certify{{ $font['id'] }}'; src: url('{{ route('fonts.file', $font['id']) }}') format('truetype'); font-weight:400; font-style:normal; font-display: swap; }</style>
        @foreach(['B' => ['700','normal'], 'I' => ['400','italic'], 'BI' => ['700','italic']] as $variantStyle => $cssStyle)
            @if(\App\Support\FontLibrary::face($font, $variantStyle))
                <style>@font-face { font-family: 'Certify{{ $font['id'] }}'; src: url('{{ route('fonts.file', ['fontId' => $font['id'], 'style' => $variantStyle]) }}') format('truetype'); font-weight:{{ $cssStyle[0] }}; font-style:{{ $cssStyle[1] }}; font-display:swap; }</style>
            @endif
        @endforeach
    @endif
@endforeach
@php
    $fontVariants = collect($fonts)->mapWithKeys(fn ($font) => [ $font['id'] => empty($font['built_in'])
        ? array_map(fn ($style) => \App\Support\FontLibrary::face($font, $style) !== null, ['B' => 'B', 'I' => 'I', 'BI' => 'BI'])
        : ['B' => true, 'I' => true, 'BI' => true]
    ])->all();
@endphp
<script>window.CertifyEditorConfig = @json($editorConfig); window.CertifyFontCss = @json($fontCss); window.CertifyFontVariants = @json($fontVariants);</script>
<script>window.CertifySignatureConfig = @json($signatureConfig);</script>
@php
    $certifyDraftConfig = [
        'url' => route('templates.draft.show', $template),
        'csrf' => csrf_token(),
        'baseline' => \App\Services\EditorDraftService::fingerprint($template),
    ];
@endphp
<script>window.CertifyDraftConfig = @json($certifyDraftConfig);</script>
<script src="{{ asset('js/certify-editor.js') }}?v=20261017-1" defer></script>
<script src="{{ asset('js/certify-editor-draft.js') }}?v=20261017-1" defer></script>
<script src="{{ asset('js/certify-fontpicker-word-v4.js') }}?v=20261017-6" defer></script>
@endsection
