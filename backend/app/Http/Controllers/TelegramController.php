<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\PendingRegistration;
use App\Models\PendingTelegramRecord;
use App\Models\ProcessedTelegramUpdate;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\CategoryGoalProgressService;
use App\Services\RecordSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramController extends Controller
{
    public function __construct(
        private readonly CategoryGoalProgressService $goalProgress,
        private readonly RecordSafety $recordSafety,
    ) {}

    public function webhook(Request $request)
    {
        $secret = trim((string) SystemSetting::read('telegram_webhook_secret', config('services.telegram.webhook_secret')));
        if (app()->isProduction() && $secret === '') {
            abort(503, 'O webhook do Telegram não está configurado com segurança.');
        }
        if ($secret && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $updateId = (string) $request->input('update_id', '');
        if ($updateId !== '' && ! $this->claimUpdate($updateId)) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $callback = $request->input('callback_query');
        if ($callback) {
            try {
                $this->handleCallback($callback);
            } catch (ValidationException $exception) {
                $this->replyValidationFailure((string) data_get($callback, 'message.chat.id'), $exception);
            }

            return response()->json(['ok' => true]);
        }

        $message = $request->input('message') ?? $request->input('edited_message');
        if (! $message) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) data_get($message, 'chat.id');
        $telegramUserId = (string) data_get($message, 'from.id');
        $username = ltrim((string) data_get($message, 'from.username'), '@');
        $text = trim((string) data_get($message, 'text', data_get($message, 'caption', '')));

        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $telegramUserId, $username, $text);

            return response()->json(['ok' => true]);
        }

        $user = User::where('telegram_user_id', $telegramUserId)->where('status', 'active')->first();
        if (! $user) {
            if (preg_match('/^(?:c[oó]digo\s*:?\s*)?(\d{6})$/iu', $text, $matches)) {
                $this->connectWithCode($chatId, $telegramUserId, $username, $matches[1]);

                return response()->json(['ok' => true]);
            }

            $this->reply($chatId, 'Esta conta do Telegram não está vinculada a um cliente ativo. Gere um código de conexão no sistema da Mia.');

            return response()->json(['ok' => true]);
        }

        $correction = PendingTelegramRecord::where('user_id', $user->id)
            ->where('status', 'awaiting_correction')->where('expires_at', '>', now())->latest()->first();
        $context = $correction ? $this->correctionContext($correction) : null;
        $voiceFileId = (string) data_get($message, 'voice.file_id', '');
        $voiceDuration = (int) data_get($message, 'voice.duration', 0);
        $voiceFileSize = (int) data_get($message, 'voice.file_size', 0);

        if ($voiceFileId !== '' && ($voiceDuration > RecordSafety::MAX_AUDIO_SECONDS || $voiceFileSize > RecordSafety::MAX_AUDIO_BYTES)) {
            $this->reply($chatId, 'Áudio Muito Longo');

            return response()->json(['ok' => true]);
        }
        if ($voiceFileId !== '' && $voiceDuration < 1) {
            $this->reply($chatId, 'Não foi possível validar a duração do áudio. Nenhum registro foi criado.');

            return response()->json(['ok' => true]);
        }

        try {
            $parsed = $voiceFileId !== ''
                ? $this->parseVoice($voiceFileId, $voiceDuration, $voiceFileSize, $user, $context)
                : $this->parseText($text, $user, $context);
            $this->handleParsed($parsed, $user, $chatId, $updateId ?: null, $correction);
        } catch (ValidationException $exception) {
            $this->replyValidationFailure($chatId, $exception);
        } catch (\Throwable $e) {
            Log::warning('Falha ao processar atualização do Telegram com dados sensíveis omitidos.', [
                'exception_class' => $e::class,
                'update_hash' => $updateId !== '' ? hash('sha256', $updateId) : null,
            ]);
            $this->reply($chatId, 'Não consegui interpretar essa mensagem. Inclua descrição, valor e data ou envie um áudio mais claro.');
        }

        return response()->json(['ok' => true]);
    }

    public function ingestBankNotification(User $user, string $text, string $sourceReference): string
    {
        $chatId = (string) $user->telegram_chat_id;
        abort_if($chatId === '', 422, 'O Telegram do cliente não está conectado.');

        try {
            $parsed = $this->parseText($text, $user, null, 'finance');
            $data = $parsed['data'] ?? [];
            if (($parsed['kind'] ?? null) !== 'finance'
                || ! in_array($data['type'] ?? null, ['income', 'expense'], true)
                || ! is_numeric($data['amount'] ?? null)
                || (float) $data['amount'] <= 0) {
                $this->reply($chatId, 'Mia 🤖\n\nRecebi uma notificação bancária do iPhone, mas ela não continha um lançamento financeiro completo. Nenhum registro foi criado.');

                return 'rejected';
            }

            return $this->handleParsed($parsed, $user, $chatId, $sourceReference, null);
        } catch (ValidationException $exception) {
            $this->replyValidationFailure($chatId, $exception);

            return 'rejected';
        }
    }

    public function notifyUser(User $user, string $text): void
    {
        if ($user->telegram_chat_id) {
            $this->reply((string) $user->telegram_chat_id, $text);
        }
    }

    private function claimUpdate(string $updateId): bool
    {
        return ProcessedTelegramUpdate::query()->insertOrIgnore([
            'update_id' => $updateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function handleStart(string $chatId, string $telegramUserId, string $username, string $text): void
    {
        if (! preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?:\s+)(\d{6})$/', $text, $matches)) {
            $this->reply($chatId, 'Abra a área Telegram no sistema da Mia e use o link com o código temporário de 6 dígitos. Você também pode digitar o código nesta conversa.');

            return;
        }

        $this->connectWithCode($chatId, $telegramUserId, $username, $matches[1]);
    }

    private function connectWithCode(string $chatId, string $telegramUserId, string $username, string $code): void
    {
        $pendingRegistrations = PendingRegistration::where('verification_code', $code)
            ->whereNull('verification_code_used_at')
            ->where('verification_code_expires_at', '>', now())
            ->latest()
            ->limit(2)
            ->get();
        $verification = VerificationCode::where('code', $code)->whereNull('used_at')
            ->where('expires_at', '>', now())->latest()->first();

        if ($pendingRegistrations->count() + (int) ($verification !== null) > 1) {
            $this->reply($chatId, 'Este código entrou em conflito com outro vínculo. Gere um novo código no sistema da Mia.');

            return;
        }

        if ($pendingRegistrations->isNotEmpty()) {
            $this->connectPendingRegistration($pendingRegistrations->first(), $chatId, $telegramUserId, $username);

            return;
        }

        if (! $verification) {
            $this->reply($chatId, 'Código inválido ou expirado. Gere um novo código no sistema.');

            return;
        }

        $alreadyLinked = User::where('telegram_user_id', $telegramUserId)->where('id', '!=', $verification->user_id)->exists();
        if ($alreadyLinked) {
            $this->reply($chatId, 'Esta conta do Telegram já está vinculada a outro cliente. Desconecte-a antes de continuar.');

            return;
        }

        $user = $verification->user;
        $attributes = [
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'telegram_verified_at' => now(),
        ];
        if ($username !== '' && ! User::where('telegram', $username)->where('id', '!=', $user->id)->exists()) {
            $attributes['telegram'] = $username;
        }
        $user->update($attributes);

        if ($verification->purpose === 'reconnect') {
            $verification->update(['used_at' => now()]);
            $this->reply($chatId, '✅ Conta conectada à Mia. A partir de agora suas mensagens serão autenticadas pelo seu telegram_user_id.');

            return;
        }

        $this->reply($chatId, "Telegram conectado! Volte ao cadastro e confirme o código {$verification->code}.");
    }

    private function connectPendingRegistration(
        PendingRegistration $pendingRegistration,
        string $chatId,
        string $telegramUserId,
        string $username
    ): void {
        $alreadyLinked = User::where('telegram_user_id', $telegramUserId)->exists()
            || PendingRegistration::where('telegram_user_id', $telegramUserId)
                ->where('id', '!=', $pendingRegistration->id)
                ->exists();
        if ($alreadyLinked) {
            $this->reply($chatId, 'Esta conta do Telegram já está vinculada a outro cliente. Desconecte-a antes de continuar.');

            return;
        }

        $attributes = [
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'telegram_verified_at' => now(),
        ];
        $telegramInUse = $username === ''
            || User::where('telegram', $username)->exists()
            || PendingRegistration::where('telegram', $username)
                ->where('id', '!=', $pendingRegistration->id)
                ->exists();
        if (! $telegramInUse) {
            $attributes['telegram'] = $username;
        }

        $pendingRegistration->update($attributes);
        $this->reply($chatId, "Telegram conectado! Volte ao cadastro e confirme o código {$pendingRegistration->verification_code}.");
    }

    private function handleCallback(array $callback): void
    {
        $callbackId = (string) data_get($callback, 'id');
        $telegramUserId = (string) data_get($callback, 'from.id');
        $chatId = (string) data_get($callback, 'message.chat.id');
        $data = (string) data_get($callback, 'data');
        $user = User::where('telegram_user_id', $telegramUserId)->where('status', 'active')->first();

        if (! $user || ! preg_match('/^(confirm|correct):([0-9a-f-]{36})$/', $data, $matches)) {
            $this->answerCallback($callbackId, 'Ação inválida ou conta desconectada.');

            return;
        }

        $pending = PendingTelegramRecord::where('token', $matches[2])->where('user_id', $user->id)->first();
        if (! $pending || $pending->expires_at->isPast()) {
            $pending?->update(['status' => 'expired']);
            $this->answerCallback($callbackId, 'Esta confirmação expirou.');
            $this->reply($chatId, 'A confirmação expirou. Envie o lançamento novamente.');

            return;
        }

        if ($matches[1] === 'correct') {
            if ($pending->status === 'confirmed') {
                $this->answerCallback($callbackId, 'Este registro já foi confirmado.');

                return;
            }
            $pending->update(['status' => 'awaiting_correction']);
            $this->answerCallback($callbackId, 'Envie a correção.');
            $this->reply($chatId, '✏️ Certo. Envie agora o dado correto por texto ou áudio — por exemplo: “o valor correto é R$ 158,40”.');

            return;
        }

        if ($pending->status === 'confirmed' || $this->recordExistsForSource($pending->origin_update_id)) {
            $pending->update(['status' => 'confirmed']);
            $this->answerCallback($callbackId, 'Registro já confirmado.');

            return;
        }
        if ($pending->status !== 'pending') {
            $this->answerCallback($callbackId, 'Envie a correção antes de confirmar.');

            return;
        }

        $saved = $this->saveParsed($pending->payload, $user, $pending->origin_update_id, $pending->confidence);
        $progress = $saved instanceof FinanceRecord ? $this->goalProgress->forRecord($saved) : null;
        $pending->update(['status' => 'confirmed']);
        $this->answerCallback($callbackId, 'Registrado com sucesso.');
        $this->reply($chatId, $this->savedConfirmation($pending->payload, $progress));
    }

    private function handleParsed(array $parsed, User $user, string $chatId, ?string $updateId, ?PendingTelegramRecord $correction): string
    {
        $parsed = $this->recordSafety->validatedAiRecord($parsed, $user);
        $confidence = max(0, min(1, (float) ($parsed['confidence'] ?? 0)));
        $lowThreshold = (float) SystemSetting::read('telegram_min_confidence', '0.70');
        $directThreshold = (float) SystemSetting::read('telegram_direct_confidence', '0.90');
        $highAmount = (float) SystemSetting::read('telegram_confirmation_amount', '100');

        if ($confidence < $lowThreshold) {
            $percent = number_format($confidence * 100, 0, ',', '.');
            $this->reply($chatId, "Mia 🤖\n\nNão consegui entender com segurança (confiança {$percent}%). Nenhum registro foi criado. Envie novamente com mais clareza.");

            return 'rejected';
        }

        $isHighValue = ($parsed['kind'] ?? null) === 'finance'
            && (float) data_get($parsed, 'data.amount', 0) > $highAmount;
        $isDuplicate = $this->isPossibleDuplicate($parsed, $user);
        $reason = $correction ? 'correction' : ($isDuplicate ? 'duplicate' : ($isHighValue ? 'high_value' : 'low_confidence'));
        $needsConfirmation = (bool) $correction || $isDuplicate || $isHighValue || $confidence < $directThreshold;

        if ($needsConfirmation) {
            $pending = $correction ?: new PendingTelegramRecord([
                'user_id' => $user->id,
                'token' => (string) Str::uuid(),
                'telegram_chat_id' => $chatId,
                'origin_update_id' => $updateId,
            ]);
            $pending->fill([
                'kind' => (string) ($parsed['kind'] ?? 'finance'),
                'payload' => $parsed,
                'confidence' => $confidence,
                'reason' => $reason,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(30),
            ])->save();
            $this->requestConfirmation($pending, $isDuplicate);

            return 'pending_confirmation';
        }

        $saved = $this->saveParsed($parsed, $user, $updateId, $confidence);
        $progress = $saved instanceof FinanceRecord ? $this->goalProgress->forRecord($saved) : null;
        $this->reply($chatId, $this->savedConfirmation($parsed, $progress));

        return 'created';
    }

    private function parseText(string $text, User $user, ?string $context = null, string $hint = 'auto'): array
    {
        abort_if($text === '', 422);
        $this->recordSafety->assertAiInputIsSafe($text);
        $message = $context ? $context."\nCorreção informada pelo usuário: ".$text : $text;

        return $this->cognitionRequest('/parse', $this->payload($user) + ['text' => $message, 'hint' => $hint]);
    }

    private function parseVoice(string $fileId, int $duration, int $declaredFileSize, User $user, ?string $context = null): array
    {
        if ($duration > RecordSafety::MAX_AUDIO_SECONDS || $declaredFileSize > RecordSafety::MAX_AUDIO_BYTES) {
            throw ValidationException::withMessages(['voice' => 'Áudio Muito Longo']);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{1,512}$/', $fileId)) {
            throw ValidationException::withMessages(['voice' => 'O identificador do áudio é inválido.']);
        }

        $token = SystemSetting::read('telegram_bot_token', config('services.telegram.bot_token'));
        abort_unless($token, 503);
        $file = Http::connectTimeout(5)->timeout(15)
            ->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId])
            ->throw()
            ->json();
        $filePath = (string) data_get($file, 'result.file_path', '');
        $verifiedFileSize = (int) data_get($file, 'result.file_size', $declaredFileSize);
        if ($verifiedFileSize > RecordSafety::MAX_AUDIO_BYTES) {
            throw ValidationException::withMessages(['voice' => 'Áudio Muito Longo']);
        }
        if (! preg_match('/^[A-Za-z0-9_.\/-]+\.(?:ogg|oga|opus)$/', $filePath)) {
            throw ValidationException::withMessages(['voice' => 'O arquivo de áudio retornado pelo Telegram é inválido.']);
        }

        $audio = Http::connectTimeout(5)->timeout(30)
            ->get("https://api.telegram.org/file/bot{$token}/{$filePath}")
            ->throw()
            ->body();
        if (strlen($audio) > RecordSafety::MAX_AUDIO_BYTES) {
            throw ValidationException::withMessages(['voice' => 'Áudio Muito Longo']);
        }

        $url = $this->cognitionUrl('/parse-audio');
        $payload = $this->payload($user) + ['context' => $context ?: 'Mensagem recebida por áudio.'];

        return Http::connectTimeout(5)->timeout(90)
            ->attach('file', $audio, 'telegram-voice.ogg', ['Content-Type' => 'audio/ogg'])
            ->post($url, array_map(
                fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
                $payload + ['duration_seconds' => $duration],
            ))
            ->throw()->json();
    }

    private function cognitionRequest(string $path, array $payload): array
    {
        $url = $this->cognitionUrl($path);

        return Http::connectTimeout(5)->timeout(60)->post($url, $payload)->throw()->json();
    }

    private function cognitionUrl(string $path): string
    {
        $configuredUrl = (string) SystemSetting::read('cognition_url', config('services.cognition.url'));

        return $this->recordSafety->trustedCognitionBaseUrl($configuredUrl).$path;
    }

    private function payload(User $user): array
    {
        return [
            'categories' => Category::availableTo($user)->where('active', true)->get(['id', 'name', 'kind'])->toArray(),
            'primary_provider' => SystemSetting::read('ai_primary_provider', 'gemini'),
            'openai_api_key' => SystemSetting::read('openai_api_key'),
            'openai_model' => SystemSetting::read('openai_model', 'gpt-5-mini'),
            'gemini_api_key' => SystemSetting::read('gemini_api_key'),
            'gemini_model' => SystemSetting::read('gemini_model', 'gemini-3.6-flash'),
        ];
    }

    private function isPossibleDuplicate(array $parsed, User $user): bool
    {
        $data = $parsed['data'] ?? [];
        if (($parsed['kind'] ?? null) === 'finance') {
            $description = Str::lower(Str::squish((string) ($data['description'] ?? '')));

            return FinanceRecord::where('user_id', $user->id)
                ->where('type', $data['type'] ?? 'expense')
                ->where('amount', abs((float) ($data['amount'] ?? 0)))
                ->whereDate('occurred_on', $data['occurred_on'] ?? now()->toDateString())
                ->get(['description'])->contains(fn ($item) => Str::lower(Str::squish($item->description)) === $description);
        }

        $name = Str::lower(Str::squish((string) ($data['name'] ?? '')));

        return Task::where('user_id', $user->id)
            ->when($data['due_on'] ?? null, fn ($query, $date) => $query->whereDate('due_on', $date), fn ($query) => $query->whereNull('due_on'))
            ->get(['name'])->contains(fn ($item) => Str::lower(Str::squish($item->name)) === $name);
    }

    private function saveParsed(array $parsed, User $user, ?string $sourceReference, float $confidence): FinanceRecord|Task
    {
        $data = $parsed['data'] ?? [];
        $category = ! empty($data['category_id']) ? Category::availableTo($user)->find($data['category_id']) : null;
        $source = str_starts_with((string) $sourceReference, 'iphone-bank:') ? 'iphone' : 'telegram';
        if (($parsed['kind'] ?? null) === 'finance') {
            return FinanceRecord::create([
                'user_id' => $user->id,
                'category_id' => $category?->id,
                'type' => in_array($data['type'] ?? '', ['income', 'expense']) ? $data['type'] : 'expense',
                'description' => Str::limit($data['description'] ?? 'Lançamento via Telegram', 180, ''),
                'amount' => abs((float) ($data['amount'] ?? 0)),
                'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
                'source' => $source,
                'ai_confidence' => $confidence,
                'source_reference' => $sourceReference,
            ]);
        }

        return Task::create([
            'user_id' => $user->id,
            'category_id' => $category?->id,
            'name' => Str::limit($data['name'] ?? 'Atividade via Telegram', 180, ''),
            'description' => $data['description'] ?? null,
            'priority' => in_array($data['priority'] ?? '', ['low', 'medium', 'high']) ? $data['priority'] : 'medium',
            'status' => in_array($data['status'] ?? '', ['todo', 'doing', 'done']) ? $data['status'] : 'todo',
            'due_on' => $data['due_on'] ?? null,
            'completed_at' => ($data['status'] ?? '') === 'done' ? now() : null,
            'source' => $source,
            'ai_confidence' => $confidence,
            'source_reference' => $sourceReference,
        ]);
    }

    private function recordExistsForSource(?string $sourceReference): bool
    {
        if (! $sourceReference) {
            return false;
        }

        return FinanceRecord::where('source_reference', $sourceReference)->exists()
            || Task::where('source_reference', $sourceReference)->exists();
    }

    private function requestConfirmation(PendingTelegramRecord $pending, bool $duplicate): void
    {
        $parsed = $pending->payload;
        $data = $parsed['data'] ?? [];
        $percent = number_format($pending->confidence * 100, 0, ',', '.');
        $reason = $duplicate ? "\n⚠️ Encontrei um registro parecido e quero evitar duplicidade." : '';

        if ($pending->kind === 'finance') {
            $type = ($data['type'] ?? 'expense') === 'income' ? 'Entrada' : 'Despesa';
            $date = $this->formatDate($data['occurred_on'] ?? null);
            $text = "Mia 🤖\n\nEntendi:\n\n{$type}: ".($data['description'] ?? 'Lançamento')
                ."\nValor: R$ ".number_format((float) ($data['amount'] ?? 0), 2, ',', '.')
                ."\nData: {$date}\nConfiança: {$percent}%{$reason}\n\nÉ isso mesmo?";
        } else {
            $text = "Mia 🤖\n\nEntendi uma atividade:\n\n".($data['name'] ?? 'Nova atividade')
                ."\nPrazo: ".$this->formatDate($data['due_on'] ?? null)
                ."\nPrioridade: ".($data['priority'] ?? 'medium')
                ."\nConfiança: {$percent}%{$reason}\n\nÉ isso mesmo?";
        }

        $this->reply($pending->telegram_chat_id, $text, [
            'inline_keyboard' => [[
                ['text' => '✅ Confirmar', 'callback_data' => 'confirm:'.$pending->token],
                ['text' => '✏️ Corrigir', 'callback_data' => 'correct:'.$pending->token],
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $progress
     */
    private function savedConfirmation(array $parsed, ?array $progress = null): string
    {
        $data = $parsed['data'] ?? [];

        $confirmation = ($parsed['kind'] ?? '') === 'finance'
            ? '✅ Financeiro registrado: '.($data['description'] ?? 'lançamento').' — R$ '.number_format((float) ($data['amount'] ?? 0), 2, ',', '.')
            : '✅ Atividade registrada: '.($data['name'] ?? 'nova atividade');

        if (($parsed['kind'] ?? '') !== 'finance' || ! $progress) {
            return $confirmation;
        }

        $percentage = rtrim(rtrim(number_format((float) $progress['percentage'], 1, ',', '.'), '0'), ',');

        return $confirmation
            ."\n\nCategoria: {$progress['category_name']}"
            ."\nPorcentagem: {$percentage}%"
            ."\nRestante: R$ ".number_format((float) $progress['remaining_amount'], 2, ',', '.');
    }

    private function correctionContext(PendingTelegramRecord $pending): string
    {
        return 'Corrija este registro já interpretado, preservando tudo que o usuário não alterar: '
            .json_encode($pending->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'.';
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return 'não informada';
        }
        try {
            $parsed = Carbon::parse($date);

            return $parsed->isToday() ? 'hoje' : $parsed->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function reply(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $token = SystemSetting::read('telegram_bot_token', config('services.telegram.bot_token'));
        if (! $token) {
            return;
        }
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }
        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
    }

    private function answerCallback(string $callbackId, string $text): void
    {
        $token = SystemSetting::read('telegram_bot_token', config('services.telegram.bot_token'));
        if ($token && $callbackId !== '') {
            Http::timeout(8)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
                'text' => $text,
            ]);
        }
    }

    private function replyValidationFailure(string $chatId, ValidationException $exception): void
    {
        $voiceMessage = data_get($exception->errors(), 'voice.0');
        $message = $voiceMessage === 'Áudio Muito Longo'
            ? 'Áudio Muito Longo'
            : 'Conteúdo bloqueado por segurança. Remova senhas, códigos, tokens ou instruções maliciosas. Nenhum registro foi criado.';

        $this->reply($chatId, $message);
    }
}
