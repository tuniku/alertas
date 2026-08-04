<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDisparo extends Model
{
    protected $table = 'tipos_disparo';

    protected $fillable = ['nome'];

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
