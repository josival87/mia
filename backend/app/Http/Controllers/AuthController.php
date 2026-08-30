<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
    public function landing(): View
    {
        return view('landing');
    }

    public function showLogin(Request $request): View
    {
        return view('auth.login', ['adminMode' => $request->boolean('admin')]);
    }

    public function login(Request $request): RedirectResponse
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

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cpf' => ['required', 'string', 'max:14'],
            'email' => ['required', 'email', 'max:180'],
        ]);

        $pendingRegistration = DB::transaction(function () use ($data) {
            $this->deleteAbandonedLegacyRegistration($data['email'], $data['cpf']);

            Validator::make($data, [
                'cpf' => [Rule::unique('users', 'cpf')],
                'email' => [Rule::unique('users', 'email')],
            ], [
                'cpf.unique' => 'Este CPF já está cadastrado.',
                'email.unique' => 'Este e-mail já está cadastrado.',
            ])->validate();

            PendingRegistration::where('email', $data['email'])
                ->where('cpf', $data['cpf'])
                ->delete();

            return PendingRegistration::create($data + [
                'verification_code' => $this->newRegistrationCode(),
                'verification_code_expires_at' => now()->addMinutes(15),
            ]);
        });

        $request->session()->forget(['pending_user_id', 'verified_user_id']);
        $request->session()->put('pending_registration_id', $pendingRegistration->id);

        if (app()->environment(['local', 'testing'])) {
            $request->session()->flash('dev_code', $pendingRegistration->verification_code);
        }

        return redirect()->route('verification.show')
            ->with('success', 'Cadastro iniciado. Abra o bot da Mia para vincular seu ID e receber o código.');
    }

    public function showVerification(Request $request): View
    {
        $pendingRegistration = $this->pendingRegistration($request, 'pending_registration_id');
        $verificationCode = $pendingRegistration->verification_code_used_at === null
            && $pendingRegistration->verification_code_expires_at->isFuture()
            ? $pendingRegistration->verification_code
            : null;
        $botUsername = ltrim(SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente'), '@');

        return view('auth.verify', compact('verificationCode', 'botUsername'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $pendingRegistration = $this->pendingRegistration($request, 'pending_registration_id');

        if ($pendingRegistration->verification_code_used_at !== null || $pendingRegistration->verification_code_expires_at->isPast()) {
            return back()->withErrors(['code' => 'O código expirou. Refaça o cadastro para receber outro.']);
        }
        $pendingRegistration->increment('verification_attempts');
        if (! hash_equals($pendingRegistration->verification_code, $data['code'])) {
            return back()->withErrors(['code' => 'Código incorreto.']);
        }

        if (! $pendingRegistration->telegram_user_id || ! $pendingRegistration->telegram_verified_at) {
            return back()->withErrors(['code' => 'Abra primeiro o bot com o link desta página para autenticar sua conta do Telegram.']);
        }

        $pendingRegistration->update(['verification_code_used_at' => now()]);
        $request->session()->put('verified_pending_registration_id', $pendingRegistration->id);

        return redirect()->route('password.create');
    }

    public function renewVerification(Request $request): RedirectResponse
    {
        $pendingRegistration = $this->pendingRegistration($request, 'pending_registration_id');
        $pendingRegistration->update([
            'verification_code' => $this->newRegistrationCode(),
            'verification_code_expires_at' => now()->addMinutes(15),
            'verification_code_used_at' => null,
            'verification_attempts' => 0,
            'telegram' => null,
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_verified_at' => null,
        ]);
        $request->session()->forget('verified_pending_registration_id');

        if (app()->environment(['local', 'testing'])) {
            $request->session()->flash('dev_code', $pendingRegistration->verification_code);
        }

        return redirect()->route('verification.show')
            ->with('success', 'Novo código gerado. Abra novamente o bot e toque em Iniciar.');
    }

    public function showCreatePassword(Request $request): View
    {
        $this->pendingRegistration($request, 'verified_pending_registration_id');

        return view('auth.password');
    }

    public function createPassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $pendingRegistration = $this->pendingRegistration($request, 'verified_pending_registration_id');
        abort_unless(
            $pendingRegistration->verification_code_used_at
            && $pendingRegistration->telegram_user_id
            && $pendingRegistration->telegram_verified_at,
            404
        );

        $user = DB::transaction(function () use ($data, $pendingRegistration) {
            Validator::make($pendingRegistration->getAttributes(), [
                'cpf' => [Rule::unique('users', 'cpf')],
                'email' => [Rule::unique('users', 'email')],
                'telegram_user_id' => [Rule::unique('users', 'telegram_user_id')],
            ], [
                'cpf.unique' => 'Este CPF foi cadastrado por outra conta enquanto o cadastro estava em andamento.',
                'email.unique' => 'Este e-mail foi cadastrado por outra conta enquanto o cadastro estava em andamento.',
                'telegram_user_id.unique' => 'Esta conta do Telegram já foi vinculada a outro cliente.',
            ])->validate();

            $telegram = $pendingRegistration->telegram;
            if ($telegram && User::where('telegram', $telegram)->exists()) {
                $telegram = null;
            }

            $user = User::create([
                'name' => $pendingRegistration->name,
                'email' => $pendingRegistration->email,
                'cpf' => $pendingRegistration->cpf,
                'telegram' => $telegram,
                'telegram_chat_id' => $pendingRegistration->telegram_chat_id,
                'telegram_user_id' => $pendingRegistration->telegram_user_id,
                'telegram_verified_at' => $pendingRegistration->telegram_verified_at,
                'password' => $data['password'],
                'role' => 'client',
                'status' => 'active',
            ]);

            $pendingRegistration->delete();

            return $user;
        });

        $request->session()->forget([
            'pending_registration_id',
            'verified_pending_registration_id',
            'pending_user_id',
            'verified_user_id',
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Conta criada. Bem-vindo à Mia!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    private function pendingRegistration(Request $request, string $sessionKey): PendingRegistration
    {
        $id = $request->session()->get($sessionKey);
        abort_unless($id, 404);

        return PendingRegistration::findOrFail($id);
    }

    private function newRegistrationCode(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = (string) random_int(100000, 999999);
            $pendingCodeExists = PendingRegistration::where('verification_code', $code)
                ->whereNull('verification_code_used_at')
                ->where('verification_code_expires_at', '>', now())
                ->exists();
            $userCodeExists = VerificationCode::where('code', $code)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $pendingCodeExists && ! $userCodeExists) {
                return $code;
            }
        }

        throw new RuntimeException('Não foi possível gerar um código de cadastro exclusivo.');
    }

    private function deleteAbandonedLegacyRegistration(string $email, string $cpf): void
    {
        $legacyUser = User::where('email', $email)
            ->where('cpf', $cpf)
            ->where('role', 'client')
            ->where('status', 'pending')
            ->first();

        if ($legacyUser && VerificationCode::where('user_id', $legacyUser->id)
            ->where('purpose', 'registration')
            ->exists()) {
            $legacyUser->delete();
        }
    }
}
