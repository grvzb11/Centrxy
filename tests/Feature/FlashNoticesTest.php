<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashNoticesTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_feedback_has_global_auto_dismiss_controls(): void
    {
        $this->withSession([
            'success' => 'Name placement saved.',
            'warning' => 'A non-critical warning.',
            'info' => 'Changes are available.',
        ])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Name placement saved.')
            ->assertSee('A non-critical warning.')
            ->assertSee('Changes are available.')
            ->assertSee('data-dismiss-after="5000"', false)
            ->assertSee('data-certify-flash', false)
            ->assertSee('certify-notices.js', false)
            ->assertSee('certify-notices.css', false)
            ->assertSee('Dismiss notification');
    }
}
