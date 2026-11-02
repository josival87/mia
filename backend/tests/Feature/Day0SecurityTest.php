<?php

namespace Tests\Feature;

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class Day0SecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_requires_accepting_terms_and_no_longer_asks_for_cpf(): void
    {
        $email = 'sem-aceite-'.Str::lower(Str::random(8)).'@example.test';

        $this->post(route('register.store'), ['name' => 'Sem Aceite', 'email' => $email])
            ->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseMissing('pending_registrations', ['email' => $email]);

        $this->get(route('register'))->assertOk()->assertDontSee('name="cpf"', false)->assertSee(route('legal.privacy'));
    }

    public function test_verification_code_is_invalidated_after_five_wrong_attempts(): void
    {
        $email = 'forca-bruta-'.Str::lower(Str::random(8)).'@example.test';
        $this->post(route('register.store'), ['name' => 'Teste', 'email' => $email, 'accept_terms' => '1']);
        $pending = PendingRegistration::where('email', $email)->firstOrFail();
        $wrong = $pending->verification_code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('verification.verify'), ['code' => $wrong]);
        }
        $this->post(route('verification.verify'), ['code' => $pending->verification_code])
            ->assertSessionHasErrors('code');

        $this->assertTrue($pending->fresh()->verification_code_expires_at->isPast());
    }

    public function test_login_is_rate_limited(): void
    {
        \Illuminate\Support\Facades\RateLimiter::clear('login');
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'admin@mia.local', 'password' => 'errada'.$i]);
        }

        $this->post(route('login.store'), ['email' => 'admin@mia.local', 'password' => 'errada'])->assertStatus(429);
    }

    public function test_legal_pages_are_public(): void
    {
        $this->get(route('legal.terms'))->assertOk()->assertSee('Termos de Uso');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Não coletamos CPF');
    }
}
