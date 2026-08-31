<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\Task;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordSafety
{
    public const MAX_AI_TEXT_CHARACTERS = 2000;

    public const MAX_AUDIO_SECONDS = 30;

    public const MAX_AUDIO_BYTES = 4 * 1024 * 1024;

    public function __construct(private readonly BankMessageSafety $messageSafety) {}

    public function assertAiInputIsSafe(string $text): void
    {
        $this->assertSafeText($text, 'message', self::MAX_AI_TEXT_CHARACTERS);

        if ($this->messageSafety->containsAiInstructionAttack($text)) {
            throw ValidationException::withMessages([
                'message' => 'A mensagem contém instruções que não podem ser enviadas à IA.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{kind: string, confidence: float, data: array<string, mixed>}
     */
    public function validatedAiRecord(array $parsed, User $user): array
    {
        $validated = Validator::make($parsed, [
            'kind' => ['required', Rule::in(['finance', 'task'])],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:40'],
            'audio_provider' => ['sometimes', 'nullable', 'string', 'max:40'],
            'transcript' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_AI_TEXT_CHARACTERS],
            'data' => ['required', 'array:type,description,amount,occurred_on,name,priority,status,due_on,category_id'],
            'data.type' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'finance'), Rule::in(['income', 'expense'])],
            'data.description' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'finance'), 'string', 'max:2000'],
            'data.amount' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'finance'), 'numeric', 'min:0.01', 'max:999999999999.99'],
            'data.occurred_on' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'finance'), 'date_format:Y-m-d'],
            'data.name' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'task'), 'string', 'max:180'],
            'data.priority' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'task'), Rule::in(['low', 'medium', 'high'])],
            'data.status' => ['nullable', Rule::requiredIf(fn (): bool => ($parsed['kind'] ?? null) === 'task'), Rule::in(['todo', 'doing', 'done'])],
            'data.due_on' => ['nullable', 'date_format:Y-m-d'],
            'data.category_id' => ['nullable', 'integer'],
        ], [
            'data.array' => 'A IA retornou campos não permitidos.',
            'data.amount.max' => 'O valor informado excede o limite seguro.',
        ])->validate();

        $kind = (string) $validated['kind'];
        $data = $validated['data'];
        $description = (string) ($data['description'] ?? '');
        $name = (string) ($data['name'] ?? '');

        if ($description !== '') {
            $this->assertSafeText($description, 'description', $kind === 'finance' ? 180 : 2000);
        }
        if ($name !== '') {
            $this->assertSafeText($name, 'name', 180);
        }
        if (is_string($validated['transcript'] ?? null)) {
            $this->assertSafeText($validated['transcript'], 'transcript', self::MAX_AI_TEXT_CHARACTERS);
        }

        $categoryId = $data['category_id'] ?? null;
        if ($categoryId !== null) {
            $expectedKind = $kind === 'finance' ? (string) $data['type'] : 'task';
            $this->assertCategoryIsAllowed((int) $categoryId, $user->id, $expectedKind, true);
        }

        $safeData = $kind === 'finance'
            ? [
                'type' => $data['type'],
                'description' => $description,
                'amount' => (float) $data['amount'],
                'occurred_on' => $data['occurred_on'],
                'category_id' => $categoryId,
            ]
            : [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'priority' => $data['priority'],
                'status' => $data['status'],
                'due_on' => $data['due_on'] ?? null,
                'category_id' => $categoryId,
            ];

        return [
            'kind' => $kind,
            'confidence' => (float) $validated['confidence'],
            'data' => $safeData,
        ];
    }

    public function assertFinanceRecordIsSafe(FinanceRecord $record): void
    {
        $attributes = $record->getAttributes();
        $data = [
            'user_id' => $attributes['user_id'] ?? null,
            'category_id' => $attributes['category_id'] ?? null,
            'type' => $attributes['type'] ?? null,
            'title' => $attributes['title'] ?? null,
            'description' => $attributes['description'] ?? null,
            'amount' => $attributes['amount'] ?? null,
            'occurred_on' => $this->dateValue($record->getAttribute('occurred_on')),
            'source' => $attributes['source'] ?? 'manual',
            'ai_confidence' => $attributes['ai_confidence'] ?? null,
            'source_reference' => $attributes['source_reference'] ?? null,
        ];

        Validator::make($data, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9][a-z0-9:_-]*$/'],
            'ai_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'source_reference' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
        ])->validate();

        $this->assertSafeText((string) $data['description'], 'description', 255);
        if (filled($data['title'])) {
            $this->assertSafeText((string) $data['title'], 'title', 180);
        }
        if ($data['category_id'] !== null) {
            $this->assertCategoryIsAllowed((int) $data['category_id'], (int) $data['user_id'], (string) $data['type']);
        }
    }

    public function assertTaskIsSafe(Task $task): void
    {
        $attributes = $task->getAttributes();
        $data = [
            'user_id' => $attributes['user_id'] ?? null,
            'category_id' => $attributes['category_id'] ?? null,
            'name' => $attributes['name'] ?? null,
            'description' => $attributes['description'] ?? null,
            'priority' => $attributes['priority'] ?? 'medium',
            'status' => $attributes['status'] ?? 'todo',
            'due_on' => $this->dateValue($task->getAttribute('due_on')),
            'completed_at' => $task->getAttribute('completed_at'),
            'source' => $attributes['source'] ?? 'manual',
            'ai_confidence' => $attributes['ai_confidence'] ?? null,
            'source_reference' => $attributes['source_reference'] ?? null,
        ];

        Validator::make($data, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'completed_at' => ['nullable', 'date'],
            'source' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9][a-z0-9:_-]*$/'],
            'ai_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'source_reference' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
        ])->validate();

        $this->assertSafeText((string) $data['name'], 'name', 180);
        if (filled($data['description'])) {
            $this->assertSafeText((string) $data['description'], 'description', 2000);
        }
        if ($data['category_id'] !== null) {
            $this->assertCategoryIsAllowed((int) $data['category_id'], (int) $data['user_id'], 'task');
        }
    }

    public function trustedCognitionBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        $allowedHosts = collect(config('services.cognition.allowed_hosts', []))
            ->filter(fn (mixed $host): bool => is_string($host) && $host !== '')
            ->map(fn (string $host): string => mb_strtolower(trim($host)));

        $isSafe = is_array($parts)
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && isset($parts['host'])
            && $allowedHosts->contains(mb_strtolower((string) $parts['host']))
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);

        if (! $isSafe) {
            throw ValidationException::withMessages([
                'cognition_url' => 'A URL do serviço de IA não pertence à lista de destinos confiáveis.',
            ]);
        }

        return $url;
    }

    private function assertSafeText(string $text, string $field, int $maxCharacters): void
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > $maxCharacters || ! mb_check_encoding($text, 'UTF-8')) {
            throw ValidationException::withMessages([
                $field => 'O texto está vazio, inválido ou excede o limite permitido.',
            ]);
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 1) {
            throw ValidationException::withMessages([
                $field => 'O texto contém caracteres de controle não permitidos.',
            ]);
        }

        if ($this->messageSafety->containsAuthenticationSecret($text)) {
            throw ValidationException::withMessages([
                $field => 'Remova senhas, códigos de autenticação, chaves e tokens antes de salvar.',
            ]);
        }
    }

    private function assertCategoryIsAllowed(int $categoryId, int $userId, string $kind, bool $mustBeActive = false): void
    {
        $category = Category::query()
            ->whereKey($categoryId)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
            ->when($mustBeActive, fn ($query) => $query->where('active', true))
            ->first();

        if (! $category || $category->kind !== $kind) {
            throw ValidationException::withMessages([
                'category_id' => 'A categoria não pertence ao usuário ou é incompatível com o registro.',
            ]);
        }
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
