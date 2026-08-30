<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalIntegration
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->bearerToken();

        if (! is_string($providedKey) || $providedKey === '') {
            return $this->unauthenticated();
        }

        foreach (config('services.external_finance.integrations', []) as $slug => $integration) {
            $configuredKey = SystemSetting::read(
                $integration['setting'],
                $integration['key'] ?? null,
            );

            if (is_string($configuredKey) && $configuredKey !== '' && hash_equals($configuredKey, $providedKey)) {
                $request->attributes->set('external_integration', $slug);
                $request->attributes->set('external_integration_label', $integration['label']);

                return $next($request);
            }
        }

        return $this->unauthenticated();
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'message' => 'Chave de integração ausente ou inválida.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
