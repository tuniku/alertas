<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));

        return Lead::query()
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = '%'.$busca.'%';

                // Agrupado num where aninhado para o OR não "escapar" e
                // anular os demais filtros da consulta.
                $q->where(function ($sub) use ($termo) {
                    $sub->where('titulo', 'like', $termo)
                        ->orWhere('pessoa_nome', 'like', $termo)
                        ->orWhere('pessoa_email', 'like', $termo)
                        ->orWhere('pessoa_telefone', 'like', $termo)
                        ->orWhere('organizacao_nome', 'like', $termo)
                        ->orWhere('origem', 'like', $termo);
                });
            })
            ->when(
                $request->filled('origem'),
                fn ($q) => $q->where('origem', $request->query('origem'))
            )
            ->orderByDesc('recebido_em')
            ->paginate(50);
    }

    public function show(Lead $lead)
    {
        return $lead;
    }

    /** Origens distintas já recebidas, para montar o filtro na tela. */
    public function origens()
    {
        return Lead::query()
            ->whereNotNull('origem')
            ->distinct()
            ->orderBy('origem')
            ->pluck('origem');
    }
}
