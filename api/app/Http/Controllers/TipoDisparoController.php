<?php

namespace App\Http\Controllers;

use App\Models\AlertaAtivo;
use App\Models\TipoDisparo;
use App\Notificacoes\MensagemAlerta;
use App\Notificacoes\NotificadorFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class TipoDisparoController extends Controller
{
    public function index()
    {
        return TipoDisparo::withCount('alertas')->orderBy('nome')->get();
    }

    /**
     * Metadados dos drivers disponíveis: o frontend usa para montar o
     * seletor e os campos de configuração de cada tipo dinamicamente,
     * sem precisar ser alterado quando um driver novo é adicionado.
     */
    public function drivers()
    {
        return NotificadorFactory::paraInterface();
    }

    public function store(Request $request)
    {
        return response()->json(TipoDisparo::create($this->validar($request)), 201);
    }

    public function show(TipoDisparo $tipoDisparo)
    {
        return $tipoDisparo;
    }

    public function update(Request $request, TipoDisparo $tipoDisparo)
    {
        $tipoDisparo->update($this->validar($request, $tipoDisparo));

        return $tipoDisparo;
    }

    public function destroy(TipoDisparo $tipoDisparo)
    {
        $tipoDisparo->delete();

        return response()->noContent();
    }

    /**
     * Envia uma notificação de teste com dados fictícios, para conferir
     * a configuração sem precisar esperar um alerta real acontecer.
     */
    public function testar(TipoDisparo $tipoDisparo)
    {
        $mensagem = new MensagemAlerta(
            projeto: 'Teste',
            alerta: 'Notificação de teste',
            codigo: 'teste-configuracao',
            importancia: 5,
            descricao: "Se você está lendo isto, o canal \"{$tipoDisparo->nome}\" está configurado corretamente.",
            eventoEm: null,
            recebidoEm: now()->format('d/m/Y H:i:s'),
        );

        try {
            NotificadorFactory::criar($tipoDisparo->driver)->enviar($mensagem, $tipoDisparo);

            return response()->json(['mensagem' => 'Notificação de teste enviada.']);
        } catch (Throwable $e) {
            return response()->json([
                'mensagem' => 'Falha ao enviar: '.$e->getMessage(),
            ], 422);
        }
    }

    private function validar(Request $request, ?TipoDisparo $tipoDisparo = null): array
    {
        $driver = $request->input('driver', $tipoDisparo?->driver);

        $regras = [
            'nome' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in(array_keys(NotificadorFactory::disponiveis()))],
            'ativo' => ['boolean'],
        ];

        // Cada driver declara as regras da própria configuração — assim
        // um driver novo (Telegram, Tuya) traz suas validações junto,
        // sem tocar neste controller.
        $regras += NotificadorFactory::regrasDe($driver);

        return $request->validate($regras);
    }
}
