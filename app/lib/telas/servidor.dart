import 'package:flutter/material.dart';

import '../api.dart';
import '../config.dart';

/// Diálogo de escolha do servidor, aberto pela tela de login.
///
/// Existe para que um mesmo APK sirva para produção e desenvolvimento —
/// caso contrário seria preciso gerar um APK por ambiente, com o
/// endereço embutido em tempo de compilação.
///
/// Devolve `true` se o endereço foi alterado.
class DialogoServidor extends StatefulWidget {
  const DialogoServidor({super.key});

  @override
  State<DialogoServidor> createState() => _DialogoServidorState();
}

class _DialogoServidorState extends State<DialogoServidor> {
  late final TextEditingController _url =
      TextEditingController(text: Api.instancia.baseUrl);

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  Future<void> _salvar() async {
    final valor = _url.text.trim();
    if (valor.isEmpty) return;

    await Api.instancia.definirBaseUrl(valor);

    if (!mounted) return;
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Servidor'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _url,
            keyboardType: TextInputType.url,
            autocorrect: false,
            decoration: const InputDecoration(
              labelText: 'Endereço da API',
              border: OutlineInputBorder(),
              helperText: 'O "/api" no final é acrescentado se faltar.',
              helperMaxLines: 2,
            ),
          ),
          const SizedBox(height: 16),
          const Text('Atalhos'),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              ActionChip(
                label: const Text('Produção'),
                onPressed: () => setState(() => _url.text = urlProducao),
              ),
              ActionChip(
                label: const Text('Local (USB)'),
                onPressed: () => setState(() => _url.text = urlLocal),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Trocar de servidor encerra a sessão atual: o token vale '
            'apenas no servidor que o emitiu.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Cancelar'),
        ),
        FilledButton(onPressed: _salvar, child: const Text('Salvar')),
      ],
    );
  }
}
