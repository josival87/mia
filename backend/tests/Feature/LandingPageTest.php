<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_keeps_login_and_registration_actions_visible_on_mobile(): void
    {
        $response = $this->get(route('landing'));
        $stylesheet = file_get_contents(public_path('css/landing.css'));

        $response
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('>Entrar</a>', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('>Criar conta</a>', false);
        $this->assertIsString($stylesheet);
        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*800px\).*?\.landing-header\s+\.btn-ghost\s*\{\s*display:\s*inline-flex;/s',
            $stylesheet,
        );
    }
}
