<?php

namespace Tests\Feature;

use App\Support\FontLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_font_library_keeps_existing_fonts_without_the_extra_catalog_panel(): void
    {
        Storage::fake('local');
        $this->assertCount(10, FontLibrary::curated());
        $this->get(route('fonts.index'))
            ->assertOk()
            ->assertSee('Font Library')
            ->assertSee('Available fonts')
            ->assertSee('Install font')
            ->assertDontSee('More certificate fonts')
            ->assertDontSee('font-curated')
            ->assertDontSee(route('fonts.catalog.install', ['catalogId' => 'great-vibes']));
        $this->assertCount(6, FontLibrary::choices());
        $this->assertSame([], FontLibrary::installed());
    }

    public function test_unknown_catalog_font_cannot_trigger_download(): void
    {
        Storage::fake('local');
        $this->post(route('fonts.catalog.install', ['catalogId' => 'not-in-the-catalog']))
            ->assertNotFound();
        $this->assertSame([], FontLibrary::installed());
    }

    public function test_catalog_install_route_allows_all_ten_font_requests_per_minute(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('fonts.catalog.install');
        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertCount(10, FontLibrary::curated());
    }

    public function test_existing_built_in_font_ids_are_unchanged(): void
    {
        $this->assertSame(
            ['dejavuserif', 'dejavusans', 'dejavusansmono', 'times', 'helvetica', 'courier'],
            array_column(FontLibrary::defaults(), 'id')
        );
    }
}
