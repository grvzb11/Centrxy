<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalRoutesTest extends TestCase
{
    public function test_consolidated_legal_page_is_available_with_security_headers(): void
    {
        $this->get(route('legal.index'))
            ->assertOk()
            ->assertSee('Legal &amp; Privacy', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_old_document_links_redirect_to_consolidated_sections(): void
    {
        foreach (['privacy', 'terms', 'cookies', 'accessibility', 'licenses'] as $slug) {
            $this->get(route('legal.document', $slug))
                ->assertRedirect(route('legal.index').'#'.$slug);
        }

        $this->get('/legal/anything-else')->assertNotFound();
    }
}
