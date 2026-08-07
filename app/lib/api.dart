import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';
import 'modelos.dart';

/// Camada de acesso à API do sistema de alertas.
///
/// Guarda o token do Sanctum e o anexa em toda requisição autenticada.
/// É uma classe simples (sem injeção de dependência nem gerenciador de
/// estado): o app tem cinco chamadas no total, e uma estrutura maior
/// custaria mais para entender do que economiza.
class Api {
  Api._();

  static final Api instancia = Api._();

  static const _chaveToken = 'token';
  static const _chaveUsuario = 'usuario_nome';

  String? _token;
  String? usuarioNome;

  bool get autenticado => _token != null;

  /// Recupera a sessão salva. Chamado uma vez, na abertura do app.
  Future<void> carregarSessao() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_chaveToken);
    usuarioNome = prefs.getString(_chaveUsuario);
  }

  Future<void> entrar(String email, String senha) async {
    final resposta = await http.post(
      Uri.parse('$apiBaseUrl/login'),
      headers: const {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({'email': email, 'password': senha}),
    );

    final corpo = _decodificar(resposta);

    if (resposta.statusCode != 200) {
      // O Laravel devolve 422 com {errors: {email: ["Credenciais..."]}}.
      throw ErroApi(_mensagemDeErro(corpo, 'Não foi possível entrar.'),
          status: resposta.statusCode);
    }

    _token = corpo['token'] as String;
    usuarioNome = (corpo['usuario']?['name'] ?? '') as String;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveToken, _token!);
    await prefs.setString(_chaveUsuario, usuarioNome ?? '');
  }

  /// Encerra a sessão. Se a chamada ao servidor falhar (sem rede, token
  /// já inválido), limpa localmente mesmo assim — do contrário o usuário
  /// ficaria preso numa sessão que não funciona.
  Future<void> sair() async {
    try {
      await http.post(
        Uri.parse('$apiBaseUrl/logout'),
        headers: _cabecalhos(),
      );
    } catch (_) {
      // ignorado de propósito
    }

    _token = null;
    usuarioNome = null;

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_chaveToken);
    await prefs.remove(_chaveUsuario);
  }

  Future<List<AlertaAtivo>> alertasAtivos() async {
    final json = await _get('/app/alertas-ativos');

    return (json['data'] as List)
        .map((e) => AlertaAtivo.doJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<AlertaLog>> historico() async {
    final json = await _get('/app/logs');

    return (json['data'] as List)
        .map((e) => AlertaLog.doJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> fecharAlerta(int alertaAtivoId) async {
    final resposta = await http.post(
      Uri.parse('$apiBaseUrl/app/alertas-ativos/$alertaAtivoId/fechar'),
      headers: _cabecalhos(),
    );

    if (resposta.statusCode >= 400) {
      final corpo = _decodificar(resposta);
      throw ErroApi(_mensagemDeErro(corpo, 'Não foi possível fechar o alerta.'),
          status: resposta.statusCode);
    }
  }

  Future<Map<String, dynamic>> _get(String caminho) async {
    final http.Response resposta;

    try {
      resposta = await http
          .get(Uri.parse('$apiBaseUrl$caminho'), headers: _cabecalhos())
          .timeout(const Duration(seconds: 15));
    } catch (e) {
      // Falha de rede não devolve corpo nem status; sem esta tradução o
      // usuário veria uma exceção técnica na tela.
      throw const ErroApi('Sem conexão com o servidor.');
    }

    final corpo = _decodificar(resposta);

    if (resposta.statusCode >= 400) {
      throw ErroApi(_mensagemDeErro(corpo, 'Erro ao consultar o servidor.'),
          status: resposta.statusCode);
    }

    return corpo;
  }

  Map<String, String> _cabecalhos() => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Map<String, dynamic> _decodificar(http.Response resposta) {
    if (resposta.body.isEmpty) return {};

    try {
      final decodificado = jsonDecode(resposta.body);
      return decodificado is Map<String, dynamic> ? decodificado : {};
    } catch (_) {
      // Resposta que não é JSON (uma página de erro do nginx, por
      // exemplo) não deve derrubar o app.
      return {};
    }
  }

  String _mensagemDeErro(Map<String, dynamic> corpo, String padrao) {
    if (corpo['mensagem'] is String) return corpo['mensagem'] as String;
    if (corpo['message'] is String && corpo['errors'] == null) {
      return corpo['message'] as String;
    }

    final erros = corpo['errors'];
    if (erros is Map && erros.isNotEmpty) {
      final primeiro = erros.values.first;
      if (primeiro is List && primeiro.isNotEmpty) return primeiro.first as String;
    }

    return padrao;
  }
}
