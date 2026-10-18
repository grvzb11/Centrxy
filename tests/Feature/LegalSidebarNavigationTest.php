<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalSidebarNavigationTest extends TestCase
{
    public function test_single_sidebar_link_and_no_legal_readiness_navigation(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('href="'.route('legal.index').'"', false)
            ->assertSee('Legal &amp; Privacy', false)
            ->assertDontSee('certify-legal-menu')
            ->assertDontSee('certify-legal-links')
            ->assertDontSee('Legal readiness');
    }

    public function test_all_five_policy_sections_are_on_one_page(): void
    {
        $this->get(route('legal.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('id="privacy"', false)
            ->assertSee('id="terms"', false)
            ->assertSee('id="cookies"', false)
            ->assertSee('id="accessibility"', false)
            ->assertSee('id="licenses"', false)
            ->assertSee('Privacy &amp; data handling', false)
            ->assertSee('Licenses &amp; credits', false)
            ->assertSee('Operator and contact');
    }

    public function test_legacy_policy_urls_redirect_to_corresponding_page_sections(): void
    {
        foreach (['privacy','terms','cookies','accessibility','licenses'] as $slug) {
            $this->get(route('legal.document', $slug))
                ->assertRedirect(route('legal.index').'#'.$slug);
        }
        $this->get('/legal/unlisted')->assertNotFound();
    }

    public function test_readiness_routes_are_unavailable(): void
    {
        $this->get('/settings/legal-readiness')->assertNotFound();
        $this->get('/settings/legal-readiness/export')->assertNotFound();
        $this->post('/settings/legal-readiness')->assertNotFound();
    }

    public function test_generate_page_has_privacy_link_to_consolidated_section(): void
    {
        $this->get(route('batches.create'))
            ->assertOk()
            ->assertSee(route('legal.index').'#privacy', false);
    }
}
