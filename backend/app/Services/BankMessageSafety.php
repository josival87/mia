<?php

namespace App\Services;

class BankMessageSafety
{
    public function containsAuthenticationSecret(string $text): bool
    {
        $credentialNearDigits = '/(?:c[oó]digo|token|otp|senha|cvv|cvc|chave\s+de\s+seguran[cç]a).{0,80}\b\d{4,8}\b|\b\d{4,8}\b.{0,80}(?:c[oó]digo|token|otp|senha|cvv|cvc|chave\s+de\s+seguran[cç]a)/iu';
        $sharingInstruction = '/(?:n[aã]o\s+compartilhe|use|digite|informe|insira).{0,50}(?:c[oó]digo|token|otp|senha)/iu';

        $credentialFormats = [
            '/\bsk-(?:proj-)?[A-Za-z0-9_-]{16,}\b/',
            '/\b(?:ghp|github_pat)_[A-Za-z0-9_]{20,}\b/',
            '/\bAKIA[A-Z0-9]{16}\b/',
            '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
            '/\b\d{6,12}:[A-Za-z0-9_-]{30,}\b/',
            '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
            '/(?:api[_ -]?key|chave\s+de\s+api|access[_ -]?token|bearer|senha)\s*[:=]\s*["\']?[A-Za-z0-9_\/.+:-]{8,}/iu',
        ];

        return preg_match($credentialNearDigits, $text) === 1
            || preg_match($sharingInstruction, $text) === 1
            || collect($credentialFormats)->contains(
                fn (string $pattern): bool => preg_match($pattern, $text) === 1,
            );
    }

    public function containsAiInstructionAttack(string $text): bool
    {
        $patterns = [
            '/(?:ignore|desconsidere|esque[cç]a|anule).{0,60}(?:instru[cç][oõ]es|regras|prompt|mensagem)\s+(?:anteriores|do\s+sistema|de\s+sistema)/iu',
            '/(?:revele|mostre|exiba|imprima|retorne|vaze).{0,60}(?:prompt|instru[cç][oõ]es\s+do\s+sistema|api[_ -]?key|chave\s+de\s+api|token|segredo)/iu',
            '/(?:system|developer|assistant)\s*:\s*(?:ignore|reveal|execute|mostre|revele)/iu',
            '/(?:execute|rode|apague|remova).{0,40}(?:drop\s+table|truncate\s+table|delete\s+from|banco\s+de\s+dados|arquivo\s+do\s+sistema)/iu',
        ];

        return collect($patterns)->contains(
            fn (string $pattern): bool => preg_match($pattern, $text) === 1,
        );
    }
}
