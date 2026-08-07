import 'package:flutter/foundation.dart';

/// Endereço da API.
///
/// A regra de resolução, em ordem:
///
/// 1. `--dart-define=API_URL=...` na linha de comando, se informado;
/// 2. em release (o APK que vai para o celular), a produção;
/// 3. em debug (emulador), a API local.
///
/// Em debug apontamos para `localhost:8000` **do próprio aparelho**, que
/// só chega ao computador porque antes rodamos:
///
/// ```
/// adb reverse tcp:8000 tcp:8000
/// ```
///
/// Esse comando abre um túnel pelo cabo USB: o que o celular pedir na
/// porta 8000 dele é entregue na porta 8000 do computador. É o caminho
/// mais simples porque não depende de Wi-Fi, de IP da rede local nem de
/// o Laravel escutar em 0.0.0.0.
///
/// (Se um dia voltarmos a usar o emulador, a alternativa sem túnel é
/// `http://10.0.2.2:8000/api` — dentro do emulador, `localhost` é o
/// próprio emulador, e `10.0.2.2` é o computador que o hospeda.)
const String _apiUrlDefinida = String.fromEnvironment('API_URL');

String get apiBaseUrl {
  if (_apiUrlDefinida.isNotEmpty) return _apiUrlDefinida;

  return kReleaseMode
      ? 'https://alertas.tuniku.com/api'
      : 'http://localhost:8000/api';
}
