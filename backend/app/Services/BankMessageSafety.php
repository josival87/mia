<?php

namespace App\Services;

class BankMessageSafety
{
    public function containsAuthenticationSecret(string $text): bool
    {
        $credentialNearDigits = '/(?:c[oó]digo|token|otp|senha|cvv|cvc|chave\s+de\s+seguran[cç]a).{0,80}\b\d{4,8}\b|\b\d{4,8}\b.{0,80}(?:c[oó]digo|token|otp|senha|cvv|cvc|chave\s+de\s+seguran[cç]a)/iu';
        $sharingInstruction = '/(?:n[aã]o\s+compartilhe|use|digite|informe|insira).{0,50}(?:c[oó]digo|token|otp|senha)/iu';

        return preg_match($credentialNearDigits, $text) === 1
            || preg_match($sharingInstruction, $text) === 1;
    }
}
