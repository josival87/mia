<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIphoneIntegration
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = $request->bearerToken();

        if (! is_string($providedToken) || ! str_starts_with($providedToken, 'mia_ios_')) {
            return $this->unauthenticated();
        }

        $user = User::query()
            ->where('iphone_ingest_token_hash', hash('sha256', $providedToken))
            ->where('role', 'client')
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return $this->unauthenticated();
        }

        $user->forceFill(['iphone_ingest_last_used_at' => now()])->save();
        $request->attributes->set('iphone_user', $user);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'message' => 'Chave do iPhone ausente, revogada ou inválida.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
