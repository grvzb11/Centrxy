<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPolicyPresentationTest extends TestCase
{
    public function test_legal_information_displays_without_unverified_approval_status(): void
    {
        config()->set('certify_legal.effective_date', '');
        config()->set('certify_legal.operator', '');
        config()->set('certify_legal.contact_email', '');

        $this->get(route('legal.index'))
            ->assertOk()
            ->assertSee('Legal &amp; Privacy', false)
            ->assertSee('Privacy &amp; data handling', false)
            ->assertSee('How these terms apply')
            ->assertSee('Not provided for this installation')
            ->assertDontSee('Draft · Needs review')
            ->assertDontSee('Not finalized legal terms')
            ->assertDontSee('Approved')
            ->assertDontSee('Document status');
    }

    public function test_configured_contact_and_date_are_displayed_without_claiming_legal_approval(): void
    {
        config()->set('certify_legal.effective_date', '2026-10-10');
        config()->set('certify_legal.operator', 'Example Operator');
        config()->set('certify_legal.contact_email', 'support@example.test');
        config()->set('certify_legal.region', 'Philippines');

        $this->get(route('legal.index'))
            ->assertOk()
            ->assertSee('Example Operator')
            ->assertSee('support@example.test')
            ->assertSee('Effective date: 2026-10-10')
            ->assertDontSee('Not provided for this installation')
            ->assertDontSee('Approved');
    }
}
