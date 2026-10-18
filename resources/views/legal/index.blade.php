@extends('layouts.app')
@section('hide_top','1')
@section('title','Legal & Privacy')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-legal-234.css') }}?v=2.0.36">
<link rel="stylesheet" href="{{ asset('css/certify-eula-addendum.css') }}?v=20261017">
@endpush
@section('content')
<div class="legal-shell clp-shell" id="legal-top">
    <header class="clp-page-heading">
        <div class="clp-heading-copy">
            <span class="legal-eyebrow">CERTRXY / INFORMATION</span>
            <h1>Legal &amp; Privacy</h1>
            <p>Information about your certificate data, using Certrxy, browser storage, accessibility, and licensing.</p>
        </div>

    </header>

    <div class="clp-layout">
        <nav class="clp-jump" aria-label="On this page">
            <span class="clp-toc-title">ON THIS PAGE</span>
            <a href="#privacy"><span class="clp-nav-number">01</span>Privacy &amp; data</a>
            <a href="#terms"><span class="clp-nav-number">02</span>Use terms</a>
            <a href="#cookies"><span class="clp-nav-number">03</span>Cookies</a>
            <a href="#accessibility"><span class="clp-nav-number">04</span>Accessibility</a>
            <a href="#licenses"><span class="clp-nav-number">05</span>Licenses &amp; credits</a>
            <a href="#eula"><span class="clp-nav-number">06</span>Software license</a>
            <a href="#operator"><span class="clp-nav-number">07</span>Operator &amp; contact</a>
            <p class="clp-toc-help">Choose a topic to jump directly to its details.</p>
        </nav>

        <div class="clp-document" aria-label="Legal and privacy information">
    <section class="clp-group" id="privacy" aria-labelledby="clp-privacy-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">01 / DATA HANDLING</span><h2 id="clp-privacy-title">Privacy &amp; data handling</h2><p>How Certrxy handles names, templates, PDFs, and other information.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section"><h3>Information handled</h3><p>Certrxy processes recipient names, optional certificate numbering, template files, fonts, saved batches, and generated PDF certificates. It also handles session information needed to use the web interface. Do not upload more personal data than you need.</p></section>
<section class="legal-section"><h3>Purpose of processing</h3><p>Names and related batch details are used to prepare, review, store, and export certificates. Templates and fonts are used to render the selected design. The application does not need a recipient’s phone number or address for basic certificate generation.</p></section>
<section class="legal-section"><h3>Where data is kept</h3><p>In the standard installation, records are stored in the configured SQLite or MySQL database and files in server-side private storage. This does not mean data is encrypted at rest. Exported template packages contain artwork and editable layout settings, but are not a backup of recipient records or the database.</p></section>
<section class="legal-section"><h3>Sharing & third parties</h3><p>As shipped, Certrxy has no built-in advertising or analytics integration. Files may be shared when an operator downloads, prints, emails, or moves them. A public deployment, added plugins, or remote storage can change who receives data; review that deployment separately.</p></section>
<section class="legal-section"><h3>Retention, access and deletion</h3><p>There is no universal automatic deletion schedule stated for this version. The operator must establish and follow a retention period and handle correction/deletion requests in the actual installation. Archived records and downloaded files may persist until separately removed.</p></section>
<section class="legal-section"><h3>Lawful basis & choices</h3><p>The operator is responsible for confirming authority to use recipients’ names, issuing appropriate notices, and honoring applicable access, correction, and deletion rights. Uploading names does not itself prove recipient consent.</p></section>
<section class="legal-section"><h3>Security limits</h3><p>Local/private use is the current deployment assumption. This version is not ready for public internet exposure without user authentication, access controls, deployment hardening, and a security assessment.</p></section>
        </div>
    </section>
    <section class="clp-group" id="terms" aria-labelledby="clp-terms-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">02 / USAGE POLICY</span><h2 id="clp-terms-title">Use terms</h2><p>Responsibilities for using and operating Certrxy.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section"><h3>Scope</h3><p>Certrxy is a certificate generation application intended primarily for controlled local/desktop use. It can generate individual certificates and combined print sheets.</p></section>
<section class="legal-section"><h3>Your content</h3><p>Only upload certificate designs, fonts, and recipient data that you are authorized to use. You are responsible for correctness of names, design, content, issuance, and rights to uploaded assets.</p></section>
<section class="legal-section"><h3>Printing and output checks</h3><p>Preview certificates and print on a test sheet before large runs. Printing results can vary by printer, margins, paper, and original artwork. No printed outcome is guaranteed.</p></section>
<section class="legal-section"><h3>Local security</h3><p>Protect the workstation, database, independent system backups, and downloaded certificates. Do not expose this deployment directly to the public internet without additional security controls.</p></section>
<section class="legal-section"><h3>Commercial use, payments, cancellations</h3><p>This source package does not include an integrated commercial checkout or subscription flow. Any future payment and refund policy must be documented separately before charges are accepted.</p></section>
<section class="legal-section"><h3>Limitations and mandatory rights</h3><p>To the extent permitted by applicable law, Certrxy is supplied without guarantees of uninterrupted operation, defect-free PDFs or suitability for a particular purpose. Any permitted limitations of liability are set out in the software EULA below. Nothing in those terms removes mandatory consumer or privacy protections, or excuses unlawful conduct.</p></section>
<section class="legal-section"><h3>How these terms apply</h3><p>These terms describe Certrxy as supplied for local use. The organization responsible for operating or distributing Certrxy may provide additional terms and contact information. Applicable law and any separate agreement continue to apply.</p></section>
        </div>
    </section>
    <section class="clp-group" id="cookies" aria-labelledby="clp-cookies-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">03 / BROWSER STORAGE</span><h2 id="clp-cookies-title">Cookies</h2><p>Essential application cookies and tracking considerations.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section"><h3>Essential application cookies</h3><p>Laravel may set a session cookie and an anti-CSRF-related cookie depending on configuration. These support session state and protecting forms and are not advertising cookies.</p></section>
<section class="legal-section"><h3>Nonessential tracking</h3><p>No advertising, user-behavior analytics, or tracking pixel integration was identified in the application source reviewed for this update. This statement does not cover browser extensions, web server monitoring, or features added later.</p></section>
<section class="legal-section"><h3>Consent assessment</h3><p>Whether consent or a banner is necessary depends on the actual deployment, purposes, and applicable law. A cookie banner alone does not establish compliance. Review any new analytics/embedded services before adding them.</p></section>
<section class="legal-section"><h3>Storage & settings</h3><p>Browser cookie lifetimes and storage can vary based on your Laravel session driver and configuration. Do not promise a fixed lifespan until the deployed configuration has been checked.</p></section>
        </div>
    </section>
    <section class="clp-group" id="accessibility" aria-labelledby="clp-accessibility-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">04 / ACCESS & SUPPORT</span><h2 id="clp-accessibility-title">Accessibility</h2><p>Accessibility efforts and known limitations.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section"><h3>Current efforts</h3><p>Certrxy provides labelled forms, keyboard focus styling, alternative text for its brand mark, a skip-to-content link, and visible notices. Decorative graphics should remain hidden from assistive technology.</p></section>
<section class="legal-section"><h3>Important limitations</h3><p>The full template canvas editor, PDF document content, contrast, zoom/reflow, and screen-reader workflows have not been certified or fully audited. A PDF preview’s visual appearance does not establish that the resulting PDF is screen-reader accessible.</p></section>
<section class="legal-section"><h3>Support and feedback</h3><p>Keyboard and assistive-technology users can report an issue to the verified operator contact once configured. Include the page, action, and a description; do not send personal recipient data unnecessarily.</p></section>
<section class="legal-section"><h3>Next checks</h3><p>Test keyboard-only navigation, focus trapping/restoration in modals, meaningful image alternatives, contrast measurements, 200% zoom, error identification, and actual PDF accessibility.</p></section>
        </div>
    </section>
    <section class="clp-group" id="licenses" aria-labelledby="clp-licenses-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">05 / OWNERSHIP &amp; CREDITS</span><h2 id="clp-licenses-title">Licenses &amp; credits</h2><p>Branding, fonts, templates, and third-party dependencies.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section"><h3>Certrxy branding</h3><p>The application includes a user-provided Certrxy logo and certificate artwork. Their copyright owner and permission terms must be confirmed by the operator.</p></section>
<section class="legal-section"><h3>Libraries and dependencies</h3><p>The application uses Laravel, NativePHP, FPDI, TCPDF, and related software; each dependency carries its own license. Check the installed versions and original license files. This page does not grant or replace third-party licenses.</p></section>
<section class="legal-section"><h3>Uploaded designs and fonts</h3><p>You must have rights to upload and use each template and font, including commercial/distribution rights where relevant. The font library requires license confirmation, but that checkbox is not legal proof of ownership.</p></section>
<section class="legal-section"><h3>Asset evidence</h3><p>Maintain source URLs, creator/owner, license type/version, acquisition date, and permission evidence for your own assets. See the project README.md for third-party licensing and asset record guidance.</p></section>
<section class="legal-section"><h3>Accurate representations</h3><p>Descriptions of features, support, security, or compliance should reflect the actual installation and be supported by evidence. Third-party endorsements must not be implied without permission.</p></section>
        </div>
    </section>
    <section class="clp-group" id="eula" aria-labelledby="clp-eula-title">
        <header class="clp-group-heading">
            <div><span class="clp-group-kicker">06 / LICENSE AGREEMENT</span><h2 id="clp-eula-title">Software license agreement</h2><p>Desktop installation and use terms.</p></div>
        </header>
        <div class="clp-details">
            <section class="legal-section">
                <h3>Read before installing or distributing</h3>
                <p class="clp-eula-summary">The same Certrxy EULA is supplied to the Windows installer. It addresses permitted use, user content, data responsibilities, warranty limitations and mandatory legal rights. The EULA does not replace third-party licenses, and it does not eliminate obligations under applicable law.</p>
                @php($certifyEulaFile = base_path('CERTRXY-EULA.txt'))
                <pre class="clp-eula-document" tabindex="0" aria-label="Certrxy End User License Agreement">{{ is_file($certifyEulaFile) ? file_get_contents($certifyEulaFile) : 'Installer EULA file missing from this installation. Contact the software publisher.' }}</pre>
                <p class="clp-eula-qualification">A license notice alone does not prove permission to distribute included fonts, artwork, or personal information. Operators must verify rights and actual privacy practices before release.</p>
            </section>
        </div>
    </section>
            <section class="clp-appendix" id="operator" aria-label="Operator and contact">
                @include('legal.partials.identity')
            </section>
            <footer class="legal-disclaimer" aria-label="About this legal information">
                <span>These notices describe the features and data practices of this Certrxy installation. They do not replace any additional notices or agreements required by the organization operating the application.</span>
                @if(config('certify_legal.effective_date'))
                    <span class="clp-policy-date">Effective date: {{ config('certify_legal.effective_date') }}</span>
                @endif
            </footer>
        </div>
    </div>
</div>
@endsection
