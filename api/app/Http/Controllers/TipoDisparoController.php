<?php

namespace App\Http\Controllers;

use App\Models\TipoDisparo;

class TipoDisparoController extends Controller
{
    /**
     * Somente listagem nesta etapa: a tabela existe como referência
     * e será detalhada junto com as integrações de notificação.
     */
    public function index()
    {
        return TipoDisparo::orderBy('nome')->get();
    }
}
