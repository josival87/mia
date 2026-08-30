<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TelegramController;
use App\Http\Requests\Api\StoreIphoneBankEventRequest;
use App\Models\BankNotificationEvent;
use App\Models\User;
use App\Services\BankMessageSafety;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IphoneBankEventController extends Controller
{
    public function store(
        StoreIphoneBankEventRequest $request,
        BankMessageSafety $safety,
        TelegramController $telegram,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->attributes->get('iphone_user');
        $data = $request->validated();
        $contentHash = hash('sha256', $data['text']);

        if (! $user->telegram_chat_id) {
            return response()->json([
                'message' => 'Conecte o Telegram à Mia antes de ativar a automação do iPhone.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $event = BankNotificationEvent::firstOrCreate(
            ['user_id' => $user->id, 'event_id' => $data['event_id']],
            [
                'channel' => $data['source'],
                'sender' => $data['sender'] ?? null,
                'content_hash' => $contentHash,
                'received_at' => $data['received_at'] ?? null,
                'status' => 'processing',
            ],
        );

        if (! $event->wasRecentlyCreated) {
            if (! hash_equals($event->content_hash, $contentHash)) {
                return response()->json([
                    'message' => 'O event_id já foi usado com outro conteúdo.',
                ], Response::HTTP_CONFLICT);
            }

            return response()->json([
                'data' => ['event_id' => $event->event_id, 'status' => $event->status],
                'meta' => ['duplicate' => true],
            ]);
        }

        if ($safety->containsAuthenticationSecret($data['text'])) {
            $event->update(['status' => 'ignored_sensitive']);
            $telegram->notifyUser($user, '🔒 Ignorei automaticamente um SMS bancário que parecia conter código, token ou senha. Nenhum dado sensível foi enviado para a IA e nenhum lançamento foi criado.');

            return response()->json([
                'data' => ['event_id' => $event->event_id, 'status' => $event->status],
                'meta' => ['duplicate' => false],
            ], Response::HTTP_ACCEPTED);
        }

        try {
            $status = $telegram->ingestBankNotification(
                $user,
                $data['text'],
                'iphone-bank:'.$event->id,
            );
            $event->update(['status' => $status]);
        } catch (\Throwable $exception) {
            $event->update(['status' => 'failed']);
            report($exception);

            return response()->json([
                'message' => 'A Mia recebeu o evento, mas não conseguiu interpretá-lo agora.',
                'data' => ['event_id' => $event->event_id, 'status' => 'failed'],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => ['event_id' => $event->event_id, 'status' => $event->status],
            'meta' => ['duplicate' => false],
        ], $status === 'created' ? Response::HTTP_CREATED : Response::HTTP_ACCEPTED);
    }
}
