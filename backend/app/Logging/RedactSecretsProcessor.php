<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Mascara tokens antes de qualquer linha de log ser escrita.
 * Erros de conexão do cliente HTTP incluem a URL inteira, e a URL do Telegram carrega o token do bot.
 */
class RedactSecretsProcessor implements ProcessorInterface
{
    private const PATTERNS = [
        '/bot\d{5,}:[A-Za-z0-9_-]{20,}/' => 'bot[REDACTED]',
        '/\bsk-(?:proj-)?[A-Za-z0-9_-]{16,}/' => 'sk-[REDACTED]',
        '/\bgsk_[A-Za-z0-9]{20,}/' => 'gsk_[REDACTED]',
        '/\bcsk-[A-Za-z0-9]{20,}/' => 'csk-[REDACTED]',
        '/\bAIza[0-9A-Za-z_-]{30,}/' => 'AIza[REDACTED]',
        '/(Bearer\s+)[A-Za-z0-9._~+\/=-]{12,}/i' => '$1[REDACTED]',
        '/\bmia_ios_[A-Za-z0-9_-]{8,}/' => 'mia_ios_[REDACTED]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::redact($record->message),
            context: self::redactArray($record->context),
        );
    }

    public static function redact(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }

    private static function redactArray(array $data): array
    {
        array_walk_recursive($data, function (&$value): void {
            if (is_string($value)) {
                $value = self::redact($value);
            }
        });

        return $data;
    }
}
