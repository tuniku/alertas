<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um token de push (FCM) registrado por um usuário a partir do
 * aplicativo Android. Existe uma linha por aparelho, não por usuário —
 * o mesmo usuário logado em dois celulares tem duas linhas.
 */
class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = ['user_id', 'token', 'plataforma'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
