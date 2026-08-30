<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IphoneIntegrationController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'client' && $user->status === 'active', 403);
        if (! $user->telegram_chat_id) {
            return back()->withErrors(['iphone' => 'Conecte o Telegram antes de gerar a chave do iPhone.']);
        }

        $token = 'mia_ios_'.Str::random(64);
        $user->forceFill([
            'iphone_ingest_token_hash' => hash('sha256', $token),
            'iphone_ingest_token_created_at' => now(),
            'iphone_ingest_last_used_at' => null,
        ])->save();

        return back()
            ->with('success', 'Chave do iPhone gerada. Copie-a agora; ela não será exibida novamente.')
            ->with('iphone_ingest_token', $token);
    }

    public function destroy(Request $request)
    {
        $request->user()->forceFill([
            'iphone_ingest_token_hash' => null,
            'iphone_ingest_token_created_at' => null,
            'iphone_ingest_last_used_at' => null,
        ])->save();

        return back()->with('success', 'Automação do iPhone revogada. A chave anterior deixou de funcionar.');
    }
}
