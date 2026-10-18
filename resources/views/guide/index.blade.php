@extends('layouts.app')
@section('title', 'User Guide')
@section('hide_top', '1')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-guide.css') }}?v=1.0.0">
@endpush
@section('content')
<div class="certify-guide">
    <header class="guide-header">
        <div>
            <span class="guide-eyebrow">CERTRXY HELP CENTER</span>
            <h1>User Guide</h1>
            <p>Everything you need to design, personalize, generate, and download certificates in Certrxy.</p>
        </div>
        <a class="guide-header-link" href="{{ route('templates.index') }}">Open templates <span aria-hidden="true">↗</span></a>
    </header>

    <nav class="guide-jump" aria-label="User Guide sections">
        <a href="#getting-started">Getting started</a>
        <a href="#templates">Templates</a>
        <a href="#editor">Editor</a>
        <a href="#fonts">Fonts</a>
        <a href="#generate">Generate</a>
        <a href="#history">Downloads &amp; History</a>
        <a href="#help">Troubleshooting</a>
    </nav>

    <section id="getting-started" class="guide-section" aria-labelledby="guide-start-heading">
        <div class="guide-section-heading"><span class="guide-section-number">01</span><div><h2 id="guide-start-heading">Start in four steps</h2><p>From a reusable design to finished PDF certificates.</p></div></div>
        <div class="guide-step-grid">
            <article class="guide-step"><span>1</span><h3>Choose a design</h3><p>Select an existing template or use a bundled design on a new installation.</p><a href="{{ route('templates.index') }}">Go to Templates →</a></article>
            <article class="guide-step"><span>2</span><h3>Personalize it</h3><p>Open <strong>Customize</strong> to place recipient names, text, numbers, and signatories.</p><a href="#editor">Editor instructions →</a></article>
            <article class="guide-step"><span>3</span><h3>Add recipients</h3><p>Enter one name per line or import a supported spreadsheet, then preview the result.</p><a href="{{ route('batches.create') }}">Start a batch →</a></article>
            <article class="guide-step"><span>4</span><h3>Download PDFs</h3><p>Find completed batches in History. Download individual PDFs, one combined PDF, or a ZIP.</p><a href="{{ route('batches.index') }}">Open History →</a></article>
        </div>
    </section>

    <section id="templates" class="guide-section" aria-labelledby="guide-templates-heading">
        <div class="guide-section-heading"><span class="guide-section-number">02</span><div><h2 id="guide-templates-heading">Manage templates</h2><p>Maintain your certificate designs in one place.</p></div></div>
        <div class="guide-content">
            <ol>
                <li>Open <a href="{{ route('templates.index') }}">Templates</a> and select <strong>New template</strong> to upload a supported PDF, PNG, or JPG design.</li>
                <li>Use <strong>Customize</strong> to edit field positions. Use <strong>Generate</strong> when the design is ready.</li>
                <li>Use the template's <strong>⋯</strong> menu for actions such as renaming, duplicating, archiving, or exporting when available.</li>
                <li>On supported local installations, <strong>Import</strong> accepts a portable Certrxy template package. Imported packages create templates rather than replacing existing designs.</li>
            </ol>
            <p class="guide-note"><strong>Built-in designs:</strong> Bundled examples are starter resources. Using one creates an editable template; editing your copy does not change the bundled original.</p>
        </div>
    </section>

    <section id="editor" class="guide-section" aria-labelledby="guide-editor-heading">
        <div class="guide-section-heading"><span class="guide-section-number">03</span><div><h2 id="guide-editor-heading">Customize a certificate</h2><p>Arrange and style fields on your certificate artwork.</p></div></div>
        <div class="guide-content">
            <ol>
                <li>From a template card, choose <strong>Customize</strong>.</li>
                <li>Use <strong>+ Add field</strong> for items such as recipient name, additional text, certificate number, award placement, or signatory blocks.</li>
                <li>Move and resize fields on the preview; choose a field to adjust its font, size, alignment, and other settings.</li>
                <li>Test the layout using a long sample name. Choose <strong>Save</strong> or <strong>Save &amp; generate</strong> when finished.</li>
            </ol>
            <p class="guide-note"><strong>Signatures:</strong> Only upload or draw a signature with the signatory's authorization. Check its position carefully before issuing certificates.</p>
        </div>
    </section>

    <section id="fonts" class="guide-section" aria-labelledby="guide-fonts-heading">
        <div class="guide-section-heading"><span class="guide-section-number">04</span><div><h2 id="guide-fonts-heading">Use built-in and custom fonts</h2><p>Choose a readable typeface that suits your design.</p></div></div>
        <div class="guide-content">
            <p>Open <a href="{{ route('fonts.index') }}">Fonts</a> to browse the available collection. <strong>Built-in</strong> fonts are provided with Certrxy, while <strong>Custom</strong> fonts are added by the user. Use the font selector in the template editor to apply a font to text fields.</p>
            <p>Keep copies and license information for fonts you install yourself. A font working locally does not necessarily permit redistribution.</p>
        </div>
    </section>

    <section id="generate" class="guide-section" aria-labelledby="guide-generate-heading">
        <div class="guide-section-heading"><span class="guide-section-number">05</span><div><h2 id="guide-generate-heading">Generate a batch</h2><p>Create personalized certificates for multiple recipients.</p></div></div>
        <div class="guide-content">
            <ol>
                <li>Open <a href="{{ route('batches.create') }}">Generate</a>, choose the certificate design, and enter a descriptive batch title.</li>
                <li>Choose <strong>Single certificate</strong>, or <strong>2 per A4 sheet</strong> for a compatible landscape design. Set print margins and an optional cutting guide.</li>
                <li>Enter recipients one per line, or use <strong>Import names</strong> for CSV, TXT, or Excel (.xlsx) files up to 25 MB.</li>
                <li>Use the sample preview to inspect names and positioning before generating the batch.</li>
            </ol>
            <p class="guide-note"><strong>Tip:</strong> Long names can overflow if the template fields are too narrow. Test a long name in the editor before generating a large batch.</p>
        </div>
    </section>

    <section id="history" class="guide-section" aria-labelledby="guide-history-heading">
        <div class="guide-section-heading"><span class="guide-section-number">06</span><div><h2 id="guide-history-heading">History, downloads, and printing</h2><p>Find, review, and export completed certificates.</p></div></div>
        <div class="guide-content">
            <p>Open <a href="{{ route('batches.index') }}">History</a> and select a completed batch. The batch details page lets you preview or download individual recipients' certificates.</p>
            <div class="guide-choice-grid">
                <div><strong>One PDF</strong><p>Combines every certificate in the batch into one multi-page PDF. For a 2-per-A4 batch, it uses the saved print-sheet layout.</p></div>
                <div><strong>Download ZIP</strong><p>Downloads individual certificate PDFs together inside a ZIP archive.</p></div>
                <div><strong>Individual download</strong><p>Use the recipient's <strong>Download</strong> action when you only need one certificate.</p></div>
                <div><strong>Trash</strong><p>Moves a batch or certificate out of active History. Restoring and permanently deleting are separate actions.</p></div>
            </div>
            <p class="guide-note"><strong>Printing:</strong> For 2-per-A4 sheets, check the PDF page dimensions, print at actual size (100%), and test one sheet before bulk printing.</p>
        </div>
    </section>

    <section id="help" class="guide-section" aria-labelledby="guide-help-heading">
        <div class="guide-section-heading"><span class="guide-section-number">07</span><div><h2 id="guide-help-heading">Frequently asked questions</h2><p>Quick answers when something does not look right.</p></div></div>
        <div class="guide-questions">
            <details><summary>My template or PDF preview looks blank. What should I do?</summary><p>Try opening the original file from the template card and refresh the page. Ensure the browser supports embedded PDFs. If the original file also fails to open, check whether the source artwork is still available.</p></details>
            <details><summary>Why can't I see Import or Export on Templates?</summary><p>Template transfer actions may be limited to local installations. They are intentionally not exposed on configurations where these operations have not been enabled.</p></details>
            <details><summary>Why does One PDF show 2 per A4 for some batches?</summary><p>That batch was generated using the two-per-A4 print layout. Other batches combine one certificate per original-sized page instead.</p></details>
            <details><summary>Can I edit or delete built-in examples?</summary><p>Bundled designs are reusable source examples. Work on an editable copy. Imported demo History entries can be managed separately without changing the original bundled resource files.</p></details>
            <details><summary>Can I undo permanent deletion?</summary><p>Permanent deletion is not an undoable action in Certrxy. Restore items while they are still in Trash and maintain your own backups of important database records and downloaded PDFs.</p></details>
        </div>
        <div class="guide-ending"><div><h3>Keep recipient information private</h3><p>Only use names, certificates, fonts, artwork, and signatures you are authorized to handle. Built-in examples may contain demo recipient data; review them before sharing a packaged app.</p></div><a href="{{ route('legal.index') }}">Legal &amp; Privacy →</a></div>
    </section>
</div>
@endsection
