<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Prova de aceite dos Termos de Uso e da Política de Privacidade (LGPD). */
class Consent extends Model
{
    /** Atualize ao publicar uma nova versão dos documentos legais. */
    public const CURRENT_VERSION = '2026-11-02';

    protected $fillable = ['user_id', 'document', 'version', 'accepted_at', 'ip_hash'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
