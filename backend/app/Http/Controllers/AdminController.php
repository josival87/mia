<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\FinanceRecord;
use App\Models\PendingTelegramRecord;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\TelegramBotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    public function dashboard()
    {
        $metrics = [
            'clients' => User::where('role', 'client')->count(),
            'active_clients' => User::where('role', 'client')->where('status', 'active')->count(),
            'finance_records' => FinanceRecord::count(),
            'tasks' => Task::count(),
        ];
        $latestClients = User::where('role', 'client')->latest()->limit(6)->get();
        $registrations = User::where('role', 'client')->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        return view('admin.dashboard', compact('metrics', 'latestClients', 'registrations'));
    }

    public function users()
    {
        $users = User::where('role', 'admin')->latest()->paginate(15);

        return view('admin.users', compact('users'));
    }

    public function storeUser(Request $request)
    {
        $request->merge(['cpf' => $request->filled('cpf') ? preg_replace('/\D+/', '', (string) $request->input('cpf')) : null]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'unique:users,cpf'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        User::create($data + ['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);

        return back()->with('success', 'Usuário administrativo criado.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'admin', 404);

        $data = $this->validateManagedUser($request, $user, ['active', 'blocked']);

        if ($request->user()?->is($user) && $data['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Você não pode bloquear o próprio acesso administrativo.',
            ]);
        }

        $user->update($data);

        return back()->with('success', 'Cadastro do administrador atualizado.');
    }

    public function clients(Request $request)
    {
        $clients = User::where('role', 'client')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'ilike', '%'.$request->q.'%')->orWhere('email', 'ilike', '%'.$request->q.'%')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.clients', compact('clients'));
    }

    public function updateClient(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'client', 404);

        $user->update($this->validateManagedUser($request, $user, ['active', 'pending', 'blocked']));

        return back()->with('success', 'Cadastro do cliente atualizado.');
    }

    public function toggleClient(User $user)
    {
        abort_unless($user->role === 'client', 404);
        $user->update(['status' => $user->status === 'active' ? 'blocked' : 'active']);

        return back()->with('success', 'Status do cliente atualizado.');
    }

    public function resetClientTelegram(User $user)
    {
        abort_unless($user->role === 'client', 404);
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

        return back()->with('success', 'Telegram anterior desconectado. Use o código para vincular uma nova conta.')
            ->with('telegram_connection_code', $code->code)
            ->with('telegram_connection_client', $user->name);
    }

    public function categories()
    {
        $categories = Category::whereNull('user_id')->orderBy('kind')->orderBy('name')->get()->groupBy('kind');

        return view('admin.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(['income', 'expense', 'task'])],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        Category::updateOrCreate(['user_id' => null, 'name' => $data['name'], 'kind' => $data['kind']], $data + ['active' => true]);

        return back()->with('success', 'Categoria global salva.');
    }

    public function destroyCategory(Category $category)
    {
        abort_unless($category->user_id === null, 403);
        $category->delete();

        return back()->with('success', 'Categoria global removida.');
    }

    public function settings(TelegramBotService $telegramBot)
    {
        $company = Company::firstOrCreate(['name' => 'Mia Assistente']);
        $settings = [
            'ai_primary_provider' => SystemSetting::read('ai_primary_provider', 'gemini'),
            'openai_model' => SystemSetting::read('openai_model', 'gpt-5-mini'),
            'gemini_model' => SystemSetting::read('gemini_model', 'gemini-3.6-flash'),
            'cognition_url' => SystemSetting::read('cognition_url', env('COGNITION_URL', 'http://cognition:8000')),
            'telegram_bot_username' => SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente'),
            'telegram_update_mode' => $telegramBot->mode(),
            'telegram_webhook_url' => SystemSetting::read('telegram_webhook_url', env('TELEGRAM_WEBHOOK_URL', url('/telegram/webhook'))),
            'telegram_confirmation_amount' => SystemSetting::read('telegram_confirmation_amount', '100'),
            'telegram_min_confidence' => SystemSetting::read('telegram_min_confidence', '0.70'),
            'telegram_direct_confidence' => SystemSetting::read('telegram_direct_confidence', '0.90'),
            'has_openai_key' => (bool) SystemSetting::read('openai_api_key'),
            'has_gemini_key' => (bool) SystemSetting::read('gemini_api_key'),
            'has_telegram_token' => (bool) SystemSetting::read('telegram_bot_token'),
            'has_webhook_secret' => (bool) SystemSetting::read('telegram_webhook_secret'),
            'has_alugapro_finance_key' => (bool) SystemSetting::read('alugapro_finance_api_key', env('ALUGAPRO_FINANCE_API_KEY')),
            'has_dashpay_finance_key' => (bool) SystemSetting::read('dashpay_finance_api_key', env('DASHPAY_FINANCE_API_KEY')),
        ];
        $telegramStatus = $telegramBot->status();

        return view('admin.settings', compact('company', 'settings', 'telegramStatus'));
    }

    public function updateSettings(Request $request, TelegramBotService $telegramBot)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'pix_key' => ['nullable', 'string', 'max:180'],
            'ai_primary_provider' => ['required', Rule::in(['gemini', 'openai'])],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'openai_model' => ['required', 'string', 'max:80'],
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_model' => ['required', 'string', 'max:80'],
            'telegram_bot_token' => ['nullable', 'string', 'max:500'],
            'telegram_bot_username' => ['required', 'string', 'max:120'],
            'telegram_update_mode' => ['required', Rule::in(['polling', 'webhook'])],
            'telegram_webhook_url' => ['nullable', 'url', 'max:500'],
            'telegram_confirmation_amount' => ['required', 'numeric', 'min:0'],
            'telegram_min_confidence' => ['required', 'numeric', 'between:0,1'],
            'telegram_direct_confidence' => ['required', 'numeric', 'between:0,1', 'gte:telegram_min_confidence'],
            'telegram_webhook_secret' => ['nullable', 'regex:/^[A-Za-z0-9_-]{16,256}$/'],
            'alugapro_finance_api_key' => ['nullable', 'string', 'min:32', 'max:500'],
            'dashpay_finance_api_key' => ['nullable', 'string', 'min:32', 'max:500'],
            'cognition_url' => ['required', 'url', 'max:255'],
        ]);

        Company::firstOrCreate(['name' => 'Mia Assistente'])->update([
            'name' => $data['company_name'], 'cnpj' => $data['cnpj'] ?? null, 'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null, 'pix_key' => $data['pix_key'] ?? null,
        ]);
        foreach (['openai_api_key', 'gemini_api_key', 'telegram_bot_token', 'telegram_webhook_secret', 'alugapro_finance_api_key', 'dashpay_finance_api_key'] as $key) {
            SystemSetting::write($key, $data[$key] ?? null, true);
        }
        foreach (['ai_primary_provider', 'openai_model', 'gemini_model', 'telegram_bot_username', 'telegram_update_mode', 'telegram_webhook_url', 'telegram_confirmation_amount', 'telegram_min_confidence', 'telegram_direct_confidence', 'cognition_url'] as $key) {
            SystemSetting::write($key, isset($data[$key]) ? (string) $data[$key] : null);
        }

        try {
            $transport = $telegramBot->synchronize();
        } catch (RuntimeException $exception) {
            return back()->with('warning', 'Configurações salvas, mas o bot ainda não foi ativado: '.$exception->getMessage());
        } catch (Throwable) {
            return back()->with('warning', 'Configurações salvas, mas não foi possível contatar o Telegram agora.');
        }

        return back()->with('success', 'Configurações salvas com segurança. '.$transport['message']);
    }

    /**
     * @param  list<string>  $allowedStatuses
     * @return array{name: string, email: string, cpf: ?string, status: string, password?: string}
     */
    private function validateManagedUser(Request $request, User $user, array $allowedStatuses): array
    {
        $submittedCpf = (string) $request->input('cpf');
        $hasSubmittedCpf = trim($submittedCpf) !== '';

        $request->merge([
            'cpf' => $hasSubmittedCpf
                ? preg_replace('/\D+/', '', $submittedCpf)
                : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'cpf' => [$hasSubmittedCpf ? 'required' : 'nullable', 'digits:11', Rule::unique('users', 'cpf')->ignore($user)],
            'status' => ['required', Rule::in($allowedStatuses)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'cpf.required' => 'O CPF deve conter 11 dígitos.',
            'cpf.digits' => 'O CPF deve conter 11 dígitos.',
            'cpf.unique' => 'Este CPF já está em uso.',
            'status.required' => 'Informe o status.',
            'status.in' => 'Selecione um status válido.',
            'password.min' => 'A nova senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        if (! $request->filled('password')) {
            unset($data['password']);
        }

        return $data;
    }
}
