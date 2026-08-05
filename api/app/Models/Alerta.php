<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'projeto_id',
        'codigo',
        'nome',
        'importancia',
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

    /**
     * Canais em que este alerta notifica. Vários por alerta: um alerta
     * crítico pode postar no Discord e acender a lâmpada ao mesmo tempo.
     */
    public function tiposDisparo(): BelongsToMany
    {
        return $this->belongsToMany(TipoDisparo::class, 'alerta_tipo_disparo')
            ->withTimestamps();
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
