<?php

namespace App\Http\Controllers;

use App\Models\AlertaLog;
use Illuminate\Http\Request;

class AlertaLogController extends Controller
{
    public function index(Request $request)
    {
        return AlertaLog::with('alerta.projeto')
            ->when($request->query('alerta_id'), fn ($q, $id) => $q->where('alerta_id', $id))
            ->when(
                $request->query('projeto_id'),
                fn ($q, $id) => $q->whereHas('alerta', fn ($a) => $a->where('projeto_id', $id))
            )
            ->orderByDesc('recebido_em')
            ->paginate(50);
    }
}
