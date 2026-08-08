import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';
import 'modelos.dart';

/// Camada de acesso à API do sistema de alertas.
///
/// Guarda o token do Sanctum e o endereço do servidor, e anexa o token
/// em toda requisição autenticada. É uma classe simples (sem injeção de
/// dependência nem gerenciador de estado): o app tem cinco chamadas no
/// total, e uma estrutura maior custaria mais para entender do que
/// economiza.
class Api {
  Api._();

  static final Api instancia = Api._();

  static const _chaveToken = 'token';
  static const _chaveUsuario = 'usuario_nome';
  static const _chaveBaseUrl = 'api_base_url';

  String? _token;
  String? usuarioNome;

  String _baseUrl = apiBaseUrlPadrao;

  String get baseUrl => _baseUrl;

  bool get autenticado => _token != null;

  /// Recupera sessão e endereço salvos. Chamado uma vez, na abertura.
  Future<void> carregarSessao() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_chaveToken);
    usuarioNome = prefs.getString(_chaveUsuario);
    _baseUrl = prefs.getString(_chaveBaseUrl) ?? apiBaseUrlPadrao;
  }

  /// Troca o servidor e **encerra a sessão atual**.
  ///
  /// O token do Sanctum vale só no servidor que o emitiu: mantê-lo ao
  /// apontar para outro ambiente resultaria em 401 a cada tela, sem o
  /// usuário entender por quê. Melhor pedir login de novo.
  Future<void> definirBaseUrl(String url) async {
    _baseUrl = normalizarUrlApi(url);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveBaseUrl, _baseUrl);

    _token = null;
    usuarioNome = null;
    await prefs.remove(_chaveToken);
    await prefs.remove(_chaveUsuario);
  }

  Future<void> entrar(String email, String senha) async {
    final http.Response resposta;

    try {
      resposta = await http
          .post(
            Uri.parse('$_baseUrl/login'),
            headers: const {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            body: jsonEncode({'email': email, 'password': senha}),
          )
          .timeout(const Duration(seconds: 15));
    } catch (_) {
      throw ErroApi('Sem conexão com $_baseUrl');
    }

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
      await http.post(Uri.parse('$_baseUrl/logout'), headers: _cabecalhos());
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

  /// Registra (ou atualiza o dono d)o token do FCM deste aparelho, para
  /// push. Chamado sempre que a tela de alertas abre — o servidor faz
  /// upsert por token, então repetir a chamada não duplica nada.
  Future<void> registrarDispositivo(String token, {String plataforma = 'android'}) async {
    final resposta = await http
        .post(
          Uri.parse('$_baseUrl/app/dispositivo'),
          headers: _cabecalhos(),
          body: jsonEncode({'token': token, 'plataforma': plataforma}),
        )
        .timeout(const Duration(seconds: 15));

    // Sem esta checagem, um 422 (validação) ou 500 do servidor passava
    // batido: o método "dava certo" sem o registro existir no banco, e
    // quem chamou (Notificacoes) não tinha como saber que falhou.
    if (resposta.statusCode >= 400) {
      throw ErroApi(
        _mensagemDeErro(_decodificar(resposta), 'Não foi possível registrar o dispositivo.'),
        status: resposta.statusCode,
      );
    }
  }

  /// Remove o registro deste aparelho, para não continuar recebendo
  /// push depois do logout.
  Future<void> removerDispositivo(String token) async {
    final resposta = await http
        .delete(
          Uri.parse('$_baseUrl/app/dispositivo'),
          headers: _cabecalhos(),
          body: jsonEncode({'token': token}),
        )
        .timeout(const Duration(seconds: 15));

    if (resposta.statusCode >= 400) {
      throw ErroApi(
        _mensagemDeErro(_decodificar(resposta), 'Não foi possível remover o dispositivo.'),
        status: resposta.statusCode,
      );
    }
  }

  Future<void> fecharAlerta(int alertaAtivoId) async {
    final http.Response resposta;

    try {
      resposta = await http
          .post(
            Uri.parse('$_baseUrl/app/alertas-ativos/$alertaAtivoId/fechar'),
            headers: _cabecalhos(),
          )
          .timeout(const Duration(seconds: 15));
    } catch (_) {
      throw const ErroApi('Sem conexão com o servidor.');
    }

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
          .get(Uri.parse('$_baseUrl$caminho'), headers: _cabecalhos())
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
