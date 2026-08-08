import 'package:flutter/material.dart';

import '../api.dart';
import '../modelos.dart';
import 'inicio.dart';
import 'servidor.dart';

class TelaLogin extends StatefulWidget {
  const TelaLogin({super.key});

  @override
  State<TelaLogin> createState() => _TelaLoginState();
}

class _TelaLoginState extends State<TelaLogin> {
  final _email = TextEditingController();
  final _senha = TextEditingController();
  bool _carregando = false;
  String? _erro;

  @override
  void dispose() {
    _email.dispose();
    _senha.dispose();
    super.dispose();
  }

  Future<void> _trocarServidor() async {
    final mudou = await showDialog<bool>(
      context: context,
      builder: (_) => const DialogoServidor(),
    );

    // setState para a tela refletir o endereço novo no rodapé.
    if (mudou == true && mounted) setState(() => _erro = null);
  }

  Future<void> _entrar() async {
    setState(() {
      _carregando = true;
      _erro = null;
    });

    try {
      await Api.instancia.entrar(_email.text.trim(), _senha.text);

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const TelaInicio()),
      );
    } on ErroApi catch (e) {
      setState(() => _erro = e.mensagem);
    } catch (_) {
      setState(() => _erro = 'Sem conexão com o servidor.');
    } finally {
      // O widget pode ter saído da árvore durante a navegação acima.
      if (mounted) setState(() => _carregando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.notifications_active, size: 64),
                const SizedBox(height: 12),
                Text(
                  'Alertas',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 32),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'E-mail',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _senha,
                  obscureText: true,
                  // Enviar direto do teclado evita ter que fechá-lo para
                  // alcançar o botão em telas pequenas.
                  onSubmitted: (_) => _carregando ? null : _entrar(),
                  decoration: const InputDecoration(
                    labelText: 'Senha',
                    border: OutlineInputBorder(),
                  ),
                ),
                if (_erro != null) ...[
                  const SizedBox(height: 16),
                  Text(
                    _erro!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _carregando ? null : _entrar,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: _carregando
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Entrar'),
                ),
                const SizedBox(height: 24),

                // O endereço fica sempre à vista: é a primeira coisa a
                // conferir quando o login falha, e evita a dúvida de
                // "estou testando produção ou desenvolvimento?".
                TextButton.icon(
                  onPressed: _carregando ? null : _trocarServidor,
                  icon: const Icon(Icons.dns_outlined, size: 18),
                  label: Text(
                    Api.instancia.baseUrl,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
