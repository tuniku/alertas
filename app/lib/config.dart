import 'package:flutter/foundation.dart';

/// Endereços da API.
///
/// O endereço em uso fica salvo no aparelho e pode ser trocado na tela de
/// login (ícone de engrenagem). Isso existe para que **um único APK**
/// sirva para produção e desenvolvimento, sem precisar recompilar.
///
/// A ordem de resolução do valor inicial, quando ainda não há nada
/// salvo:
///
/// 1. `--dart-define=API_URL=...` na linha de comando, se informado;
/// 2. em release (o APK instalado no celular), a produção;
/// 3. em debug (rodando pelo cabo), o servidor local.
const String _apiUrlDefinida = String.fromEnvironment('API_URL');

const String urlProducao = 'https://alertas.tuniku.com/api';

/// O `localhost` aqui é o **do próprio celular**. Ele só alcança o
/// computador porque antes rodamos `adb reverse tcp:8000 tcp:8000`, que
/// abre um túnel pelo cabo USB. Sem esse comando, dá "sem conexão".
const String urlLocal = 'http://localhost:8000/api';

String get apiBaseUrlPadrao {
  if (_apiUrlDefinida.isNotEmpty) return _apiUrlDefinida;

  return kReleaseMode ? urlProducao : urlLocal;
}

/// Normaliza o que o usuário digitar: tira barras no fim e acrescenta
/// `/api` se faltar — é o esquecimento mais comum, e o erro resultante
/// (404 em tudo) não deixa a causa óbvia.
String normalizarUrlApi(String url) {
  var u = url.trim();

  while (u.endsWith('/')) {
    u = u.substring(0, u.length - 1);
  }

  if (!u.endsWith('/api')) u = '$u/api';

  return u;
}
