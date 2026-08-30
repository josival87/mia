<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function landing()
    {
        return view('landing');
    }

    public function showLogin(Request $request)
    {
        return view('auth.login', ['adminMode' => $request->boolean('admin')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        if ($request->user()->status !== 'active') {
            Auth::logout();

            return back()->withErrors(['email' => 'Esta conta ainda não está ativa.']);
        }

        return redirect()->intended($request->user()->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge([
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cpf' => ['required', 'string', 'max:14', 'unique:users,cpf'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
        ]);
        $data['password'] = Hash::make(Str::random(48));
        $data['status'] = 'pending';
        $user = User::create($data);

        $code = (string) random_int(100000, 999999);
        VerificationCode::create(['user_id' => $user->id, 'code' => $code, 'purpose' => 'registration', 'expires_at' => now()->addMinutes(15)]);
        $request->session()->put('pending_user_id', $user->id);

        if (app()->environment(['local', 'testing'])) {
            $request->session()->flash('dev_code', $code);
        }

        return redirect()->route('verification.show')
            ->with('success', 'Cadastro iniciado. Abra o bot da Mia para vincular seu ID e receber o código.');
    }

    public function showVerification(Request $request)
    {
        abort_unless($request->session()->has('pending_user_id'), 404);
        $verificationCode = VerificationCode::where('user_id', $request->session()->get('pending_user_id'))
            ->where('purpose', 'registration')->whereNull('used_at')->where('expires_at', '>', now())->latest()->value('code');
        $botUsername = ltrim(SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente'), '@');

        return view('auth.verify', compact('verificationCode', 'botUsername'));
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $userId = $request->session()->get('pending_user_id');
        $verification = VerificationCode::where('user_id', $userId)->where('purpose', 'registration')->whereNull('used_at')->latest()->first();

        if (! $verification || $verification->expires_at->isPast()) {
            return back()->withErrors(['code' => 'O código expirou. Refaça o cadastro para receber outro.']);
        }
        $verification->increment('attempts');
        if (! hash_equals($verification->code, $data['code'])) {
            return back()->withErrors(['code' => 'Código incorreto.']);
        }

        $user = User::findOrFail($userId);
        if (! $user->telegram_user_id && ! app()->environment('testing')) {
            return back()->withErrors(['code' => 'Abra primeiro o bot com o link desta página para autenticar sua conta do Telegram.']);
        }

        $verification->update(['used_at' => now()]);
        $user->update(['telegram_verified_at' => now()]);
        $request->session()->put('verified_user_id', $userId);

        return redirect()->route('password.create');
    }

    public function renewVerification(Request $request)
    {
        $userId = $request->session()->get('pending_user_id');
        abort_unless($userId, 404);

        VerificationCode::where('user_id', $userId)
            ->where('purpose', 'registration')
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);
        VerificationCode::create([
            'user_id' => $userId,
            'code' => $code,
            'purpose' => 'registration',
            'expires_at' => now()->addMinutes(15),
        ]);

        if (app()->environment(['local', 'testing'])) {
            $request->session()->flash('dev_code', $code);
        }

        return redirect()->route('verification.show')
            ->with('success', 'Novo código gerado. Abra novamente o bot e toque em Iniciar.');
    }

    public function showCreatePassword(Request $request)
    {
        abort_unless($request->session()->has('verified_user_id'), 404);

        return view('auth.password');
    }

    public function createPassword(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::findOrFail($request->session()->pull('verified_user_id'));
        $request->session()->forget('pending_user_id');
        $user->update(['password' => $data['password'], 'status' => 'active']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Conta criada. Bem-vindo à Mia!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
