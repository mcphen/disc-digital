<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    public function test_help_page_is_available_without_authentication(): void
    {
        $this->get('/aide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Help'));
    }
}
