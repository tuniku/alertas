<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaLog extends Model
{
    protected $table = 'alerta_logs';

    protected $fillable = [
        'alerta_id',
        'recebido_em',
        'evento_em',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'recebido_em' => 'datetime',
            'evento_em' => 'datetime',
        ];
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(Alerta::class);
    }
}
