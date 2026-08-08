import 'dart:developer' as developer;

import 'package:firebase_messaging/firebase_messaging.dart';

import 'api.dart';

/// Integração com o FCM: permissão, obtenção do token e envio ao
/// backend.
///
/// Fica separado de [Api] porque lida com outro pacote (o SDK do
/// Firebase, e não `http` puro) e tem uma responsabilidade diferente —
/// aqui só cuida de conseguir e manter o token; quem fala com o
/// servidor propriamente continua sendo a [Api].
class Notificacoes {
  Notificacoes._();

  static final _mensagens = FirebaseMessaging.instance;

  /// Pede permissão (o Android 13+ pergunta em tempo de execução;
  /// versões anteriores concedem automaticamente) e registra o token
  /// atual no backend.
  ///
  /// Chamado sempre que a tela de alertas abre — tanto logo após o
  /// login quanto quando o app reabre com uma sessão salva — porque é
  /// idempotente no servidor (upsert por token) e cobre o caso do token
  /// ter sido trocado pelo sistema entre uma abertura e outra.
  static Future<void> registrar() async {
    // registrar() é chamado sem "await" e sem try/catch em
    // TelaInicio.initState() — de propósito, para não atrasar a
    // abertura da tela por causa do push. Isso significa que qualquer
    // exceção aqui dentro (permissão negada, Play Services indisponível,
    // getToken() falhando) precisa ser capturada NESTA função: se
    // escapar, vira um erro "solto" que o Flutter só imprime no console,
    // sem nenhum efeito visível para quem está testando.
    try {
      await _mensagens.requestPermission();

      final token = await _mensagens.getToken();
      if (token != null) {
        await _enviarAoBackend(token);
      }

      // O token pode mudar (reinstalação, dados do app limpos, rotação
      // interna do FCM); sem este listener o aparelho ficaria "mudo" até
      // o próximo login manual.
      _mensagens.onTokenRefresh.listen(_enviarAoBackend);
    } catch (e) {
      developer.log('Falha ao preparar o push (permissão ou token): $e', name: 'notificacoes');
    }
  }

  static Future<void> _enviarAoBackend(String token) async {
    try {
      await Api.instancia.registrarDispositivo(token);
    } catch (e) {
      // Falha aqui não deve travar o app: o pior caso é o aparelho não
      // receber push até a próxima tentativa, não a perda de nenhuma
      // funcionalidade de tela.
      developer.log('Falha ao registrar dispositivo para push: $e', name: 'notificacoes');
    }
  }

  /// Chamado ao sair, para não continuar recebendo push depois do
  /// logout. Best-effort — mesmo raciocínio do `Api.sair()`: se falhar
  /// (sem rede, por exemplo), o logout segue em frente do mesmo jeito.
  static Future<void> desregistrar() async {
    try {
      final token = await _mensagens.getToken();
      if (token != null) {
        await Api.instancia.removerDispositivo(token);
      }
    } catch (_) {
      // ignorado de propósito
    }
  }
}
