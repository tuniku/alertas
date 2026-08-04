<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projeto extends Model
{
    protected $table = 'projetos';

    protected $fillable = ['nome'];

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
