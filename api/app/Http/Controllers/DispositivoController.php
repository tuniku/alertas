<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;

/**
 * Registro do token do FCM de cada aparelho, para o envio de push.
 *
 * Um token é único no sistema mesmo entre usuários diferentes: se dois
 * usuários logam no mesmo celular, o token do Firebase é o mesmo, e o
 * cadastro mais recente é quem "possui" aquela linha — o usuário
 * anterior naturalmente para de receber push nesse aparelho, que é o
 * comportamento esperado (ele já não está mais logado ali).
 */
class DispositivoController extends Controller
{
    /** Chamado pelo app logo após o login, com o token do FCM obtido no aparelho. */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'plataforma' => ['nullable', 'string', 'max:32'],
        ]);

        Dispositivo::updateOrCreate(
            ['token' => $dados['token']],
            [
                'user_id' => $request->user()->id,
                'plataforma' => $dados['plataforma'] ?? 'android',
            ]
        );

        return response()->json(['mensagem' => 'Dispositivo registrado.']);
    }

    /**
     * Chamado pelo app ao sair, para não continuar recebendo push depois
     * do logout. Best-effort: se falhar (sem rede, por exemplo), o app
     * sai da sessão mesmo assim, e o dispositivo é apenas sobrescrito no
     * próximo login de alguém nesse aparelho.
     */
    public function destroy(Request $request)
    {
        $dados = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        Dispositivo::where('token', $dados['token'])->delete();

        return response()->json(['mensagem' => 'Dispositivo removido.']);
    }
}
