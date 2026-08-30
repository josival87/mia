<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionProxyTest extends TestCase
{
    public function test_forwarded_https_prefix_is_used_for_generated_urls(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeaders([
                'Host' => 'jbmj.io',
                'X-Forwarded-Host' => 'jbmj.io',
                'X-Forwarded-Prefix' => '/mia',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/');

        $response->assertOk();
        $response->assertSee('https://jbmj.io/mia/entrar', false);
        $response->assertSee('https://jbmj.io/mia/css/mia.css', false);
    }
}
