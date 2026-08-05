<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Um canal de notificação configurado (ex.: "Discord #alertas-carbel").
 * O "driver" define qual classe sabe entregar a mensagem; a
 * "configuracao" guarda os parâmetros específicos daquele driver.
 */
class TipoDisparo extends Model
{
    protected $table = 'tipos_disparo';

    protected $fillable = ['nome', 'driver', 'configuracao', 'ativo'];

    protected function casts(): array
    {
        return [
            'configuracao' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function alertas(): BelongsToMany
    {
        return $this->belongsToMany(Alerta::class, 'alerta_tipo_disparo')
            ->withTimestamps();
    }
}
