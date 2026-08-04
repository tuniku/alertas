<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'projeto_id',
        'codigo',
        'nome',
        'importancia',
        'tipo_disparo_id',
        'expiracao_minutos',
    ];

    protected function casts(): array
    {
        return [
            'importancia' => 'integer',
            'expiracao_minutos' => 'integer',
        ];
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }

    public function tipoDisparo(): BelongsTo
    {
        return $this->belongsTo(TipoDisparo::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AlertaLog::class);
    }

    public function ativos(): HasMany
    {
        return $this->hasMany(AlertaAtivo::class);
    }
}
