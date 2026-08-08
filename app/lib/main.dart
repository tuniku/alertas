import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';

import 'api.dart';
import 'telas/inicio.dart';
import 'telas/login.dart';

Future<void> main() async {
  // Necessário porque lemos o token salvo (SharedPreferences, que é
  // código nativo) antes de montar a primeira tela.
  WidgetsFlutterBinding.ensureInitialized();

  // O google-services.json (baixado no Firebase Console) já tem tudo
  // que initializeApp() precisa — sem parâmetros aqui, diferente do
  // Flutter para iOS/web.
  await Firebase.initializeApp();

  await Api.instancia.carregarSessao();

  runApp(const AplicativoAlertas());
}

class AplicativoAlertas extends StatelessWidget {
  const AplicativoAlertas({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Alertas',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1D4ED8)),
        useMaterial3: true,
      ),
      // Se já havia token salvo, o app abre direto na lista; o usuário
      // só reencontra o login se sair ou se o token for recusado.
      home: Api.instancia.autenticado ? const TelaInicio() : const TelaLogin(),
    );
  }
}
