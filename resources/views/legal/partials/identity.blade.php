<div class="legal-identity">
    <h2 id="clp-operator-title">Operator &amp; contact</h2>
    <p class="legal-identity-intro">Details of the organization or person responsible for this installation.</p>
    <dl class="legal-contact-details">
        <div>
            <dt>Operator</dt>
            <dd>
                @if(config('certify_legal.operator'))
                    {{ config('certify_legal.operator') }}@if(config('certify_legal.region')) · {{ config('certify_legal.region') }}@endif
                @else
                    Not provided for this installation
                @endif
            </dd>
        </div>
        <div>
            <dt>Privacy &amp; support contact</dt>
            <dd>
                @if(config('certify_legal.contact_email'))
                    <a href="mailto:{{ config('certify_legal.contact_email') }}">{{ config('certify_legal.contact_email') }}</a>
                @else
                    Not provided for this installation
                @endif
            </dd>
        </div>
    </dl>
    @unless(config('certify_legal.contact_email'))
        <p class="clp-contact-help">For questions about a certificate issued to you, contact the school or organization that issued it.</p>
    @endunless
</div>
