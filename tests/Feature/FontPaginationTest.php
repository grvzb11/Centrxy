<?php

namespace Tests\Feature;

use Tests\TestCase;

class FontPaginationTest extends TestCase
{
    public function test_font_library_exposes_paginated_gallery_controls(): void
    {
        $response = $this->get(route('fonts.index'));

        $response->assertOk()
            ->assertSee('id="fontGallery"', false)
            ->assertSee('id="fontPagination"', false)
            ->assertSee('id="fontPageRange"', false)
            ->assertSee('id="fontPagePrevious"', false)
            ->assertSee('id="fontPageNumbers"', false)
            ->assertSee('id="fontPageNext"', false)
            ->assertSee('css/certify-font-pagination-248.css')
            ->assertSee('js/certify-fonts.js')
            ->assertSee('data-font-filter="builtin"', false)
            ->assertSee('data-font-filter="custom"', false)
            ->assertDontSee('data-font-filter="installed"', false);
    }
}
