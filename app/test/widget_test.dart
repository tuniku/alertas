// O teste gerado pelo template do Flutter era do app de contador de
// exemplo e não compila mais (referenciava MyApp). Substituído por um
// teste mínimo da tela de login, que é a primeira coisa que o usuário vê
// e não depende de rede para ser montada.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:alertas_app/telas/login.dart';

void main() {
  testWidgets('tela de login mostra os campos e o botao', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: TelaLogin()));

    expect(find.text('E-mail'), findsOneWidget);
    expect(find.text('Senha'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Entrar'), findsOneWidget);
  });
}
