<?php

namespace App\Http\Controllers;

use App\Models\PendingTelegramRecord;
use App\Models\SystemSetting;
use App\Models\VerificationCode;
use Illuminate\Http\Request;

class TelegramConnectionController extends Controller
{
    public function show(Request $request)
    {
        $code = VerificationCode::where('user_id', $request->user()->id)
            ->where('purpose', 'reconnect')->whereNull('used_at')->where('expires_at', '>', now())
            ->latest()->first();
        $botUsername = ltrim(SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente'), '@');

        return view('telegram.connection', compact('code', 'botUsername'));
    }

    public function reconnect(Request $request)
    {
        $user = $request->user();
        VerificationCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        PendingTelegramRecord::where('user_id', $user->id)->whereIn('status', ['pending', 'awaiting_correction'])
            ->update(['status' => 'cancelled']);
        $user->update(['telegram_user_id' => null, 'telegram_chat_id' => null, 'telegram_verified_at' => null]);

        $code = VerificationCode::create([
            'user_id' => $user->id,
            'code' => (string) random_int(100000, 999999),
            'purpose' => 'reconnect',
            'expires_at' => now()->addMinutes(15),
        ]);

        return back()->with('success', 'Vínculo anterior removido. Abra o bot com o código abaixo para conectar a nova conta.')
            ->with('telegram_connection_code', $code->code);
    }

    public function disconnect(Request $request)
    {
        PendingTelegramRecord::where('user_id', $request->user()->id)->whereIn('status', ['pending', 'awaiting_correction'])
            ->update(['status' => 'cancelled']);
        $request->user()->update(['telegram_user_id' => null, 'telegram_chat_id' => null, 'telegram_verified_at' => null]);

        return back()->with('success', 'Telegram desconectado. Mensagens futuras dessa conta não serão aceitas.');
    }
}
