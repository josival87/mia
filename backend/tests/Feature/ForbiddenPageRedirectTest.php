<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForbiddenPageRedirectTest extends TestCase
{
    public function test_client_accessing_an_admin_page_redirects_to_landing(): void
    {
        $client = User::factory()->client()->make();

        $response = $this->actingAs($client)->get(route('admin.dashboard'));

        $response->assertRedirect(route('landing'));
    }

    public function test_guest_accessing_a_forbidden_page_redirects_to_landing(): void
    {
        Route::get('/forbidden-page', fn () => abort(403));

        $response = $this->get('/forbidden-page');

        $response->assertRedirect(route('landing'));
    }

    public function test_head_request_to_a_forbidden_page_redirects_to_landing(): void
    {
        Route::get('/forbidden-page', fn () => abort(403));

        $response = $this->head('/forbidden-page');

        $response->assertRedirect(route('landing'));
    }

    public function test_authorization_exception_redirects_to_landing(): void
    {
        Route::get('/forbidden-page', fn () => throw new AuthorizationException);

        $response = $this->get('/forbidden-page');

        $response->assertRedirect(route('landing'));
    }

    public function test_json_request_keeps_the_403_response(): void
    {
        $client = User::factory()->client()->make();

        $response = $this->actingAs($client)->getJson(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_api_request_without_json_headers_keeps_the_403_response(): void
    {
        Route::get('/api/forbidden-page', fn () => abort(403));

        $response = $this->get('/api/forbidden-page');

        $response->assertForbidden();
    }

    public function test_forbidden_post_request_keeps_the_403_response(): void
    {
        Route::post('/forbidden-page', fn () => abort(403));

        $response = $this->post('/forbidden-page');

        $response->assertForbidden();
    }

    public function test_missing_page_keeps_the_404_response(): void
    {
        $response = $this->get('/missing-page');

        $response->assertNotFound();
    }

    public function test_redirect_preserves_the_production_https_prefix(): void
    {
        Route::get('/forbidden-page', fn () => abort(403));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeaders([
                'Host' => 'jbmj.io',
                'X-Forwarded-Host' => 'jbmj.io',
                'X-Forwarded-Prefix' => '/mia',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/forbidden-page');

        $response->assertRedirect('https://jbmj.io/mia');
    }
}
