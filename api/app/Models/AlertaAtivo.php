<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaAtivo extends Model
{
    protected $table = 'alertas_ativos';

    protected $fillable = [
        'alerta_id',
        'ativo',
        'fechado_por',
        'fechado_em',
        'expira_em',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'fechado_em' => 'datetime',
            'expira_em' => 'datetime',
        ];
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(Alerta::class);
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    /**
     * Um registro bloqueia novos disparos enquanto estiver ativo
     * e dentro do prazo de expiração (ou sem prazo definido).
     */
    public function bloqueiaNovoDisparo(): bool
    {
        return $this->ativo
            && ($this->expira_em === null || $this->expira_em->isFuture());
    }
}
