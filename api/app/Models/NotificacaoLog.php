<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacaoLog extends Model
{
    protected $table = 'notificacao_logs';

    protected $fillable = [
        'alerta_ativo_id',
        'tipo_disparo_id',
        'driver',
        'sucesso',
        'erro',
    ];

    protected function casts(): array
    {
        return ['sucesso' => 'boolean'];
    }

    public function alertaAtivo(): BelongsTo
    {
        return $this->belongsTo(AlertaAtivo::class);
    }

    public function tipoDisparo(): BelongsTo
    {
        return $this->belongsTo(TipoDisparo::class);
    }
}
