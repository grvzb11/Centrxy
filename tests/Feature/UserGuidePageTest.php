<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserGuidePageTest extends TestCase
{
    public function test_user_guide_is_publicly_viewable_in_local_application(): void
    {
        $this->get(route('guide.index'))
            ->assertOk()
            ->assertSee('User Guide')
            ->assertSee('Start in four steps')
            ->assertSee('2 per A4 sheet')
            ->assertSee('Legal &amp; Privacy', false);
    }

    public function test_sidebar_has_links_to_guide_and_legal(): void
    {
        $this->get(route('guide.index'))
            ->assertSee('href="'.route('guide.index').'"', false)
            ->assertSee('href="'.route('legal.index').'"', false);
    }
}
