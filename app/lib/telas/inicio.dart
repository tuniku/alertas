import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api.dart';
import '../modelos.dart';
import 'login.dart';

final _formato = DateFormat('dd/MM/yyyy HH:mm');

/// Cor por severidade — a mesma escala usada no Discord, no Telegram e
/// na lâmpada: crítico (>=8) vermelho, atenção (>=4) âmbar, resto verde.
Color _corDaImportancia(int importancia) {
  if (importancia >= 8) return const Color(0xFFDC2626);
  if (importancia >= 4) return const Color(0xFFF59E0B);
  return const Color(0xFF16A34A);
}

class TelaInicio extends StatefulWidget {
  const TelaInicio({super.key});

  @override
  State<TelaInicio> createState() => _TelaInicioState();
}

class _TelaInicioState extends State<TelaInicio> {
  int _aba = 0;

  Future<void> _sair() async {
    await Api.instancia.sair();

    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const TelaLogin()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_aba == 0 ? 'Alertas ativos' : 'Histórico'),
        actions: [
          IconButton(
            tooltip: 'Sair',
            onPressed: _sair,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: IndexedStack(
        index: _aba,
        // IndexedStack em vez de trocar o widget: assim a lista já
        // carregada não é descartada ao alternar de aba.
        children: const [_AbaAtivos(), _AbaHistorico()],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _aba,
        onDestinationSelected: (i) => setState(() => _aba = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.warning_amber_outlined),
            selectedIcon: Icon(Icons.warning_amber),
            label: 'Ativos',
          ),
          NavigationDestination(
            icon: Icon(Icons.history_outlined),
            selectedIcon: Icon(Icons.history),
            label: 'Histórico',
          ),
        ],
      ),
    );
  }
}

class _AbaAtivos extends StatefulWidget {
  const _AbaAtivos();

  @override
  State<_AbaAtivos> createState() => _AbaAtivosState();
}

class _AbaAtivosState extends State<_AbaAtivos> {
  List<AlertaAtivo>? _itens;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final itens = await Api.instancia.alertasAtivos();
      if (!mounted) return;
      setState(() {
        _itens = itens;
        _erro = null;
      });
    } on ErroApi catch (e) {
      if (!mounted) return;
      setState(() => _erro = e.mensagem);
    }
  }

  Future<void> _fechar(AlertaAtivo item) async {
    final confirmou = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Fechar alerta'),
        content: Text(
          'Fechar "${item.alerta}"?\n\n'
          'O alerta será encerrado para todos os usuários, no aplicativo e no painel.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Fechar alerta'),
          ),
        ],
      ),
    );

    if (confirmou != true) return;

    try {
      await Api.instancia.fecharAlerta(item.id);
      await _carregar();

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Alerta fechado.')),
      );
    } on ErroApi catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.mensagem)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_erro != null) {
      return _Aviso(mensagem: _erro!, aoTentarNovamente: _carregar);
    }

    if (_itens == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_itens!.isEmpty) {
      return RefreshIndicator(
        onRefresh: _carregar,
        // ListView (e não Center) para que o "puxar para atualizar"
        // continue funcionando com a lista vazia.
        child: ListView(
          children: const [
            SizedBox(height: 120),
            Center(child: Text('Nenhum alerta ativo no momento.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView.separated(
        itemCount: _itens!.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (_, i) {
          final item = _itens![i];

          return ListTile(
            leading: CircleAvatar(
              backgroundColor: _corDaImportancia(item.importancia),
              foregroundColor: Colors.white,
              child: Text('${item.importancia}'),
            ),
            title: Text(item.alerta),
            subtitle: Text(
              '${item.projeto} · ${item.nivel}\n'
              'desde ${_formato.format(item.criadoEm)}'
              '${item.atualizadoEm != item.criadoEm ? ' · última ${_formato.format(item.atualizadoEm)}' : ''}',
            ),
            isThreeLine: true,
            trailing: IconButton(
              tooltip: 'Fechar alerta',
              icon: const Icon(Icons.check_circle_outline),
              onPressed: () => _fechar(item),
            ),
          );
        },
      ),
    );
  }
}

class _AbaHistorico extends StatefulWidget {
  const _AbaHistorico();

  @override
  State<_AbaHistorico> createState() => _AbaHistoricoState();
}

class _AbaHistoricoState extends State<_AbaHistorico> {
  List<AlertaLog>? _itens;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final itens = await Api.instancia.historico();
      if (!mounted) return;
      setState(() {
        _itens = itens;
        _erro = null;
      });
    } on ErroApi catch (e) {
      if (!mounted) return;
      setState(() => _erro = e.mensagem);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_erro != null) {
      return _Aviso(mensagem: _erro!, aoTentarNovamente: _carregar);
    }

    if (_itens == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_itens!.isEmpty) {
      return RefreshIndicator(
        onRefresh: _carregar,
        child: ListView(
          children: const [
            SizedBox(height: 120),
            Center(child: Text('Nenhum evento registrado.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView.separated(
        itemCount: _itens!.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (_, i) {
          final item = _itens![i];

          return ListTile(
            leading: CircleAvatar(
              backgroundColor: _corDaImportancia(item.importancia),
              foregroundColor: Colors.white,
              child: Text('${item.importancia}'),
            ),
            title: Text(item.alerta),
            subtitle: Text(
              '${item.projeto} · ${_formato.format(item.recebidoEm)}'
              '${item.descricao != null && item.descricao!.isNotEmpty ? '\n${item.descricao}' : ''}',
            ),
            isThreeLine: item.descricao != null && item.descricao!.isNotEmpty,
          );
        },
      ),
    );
  }
}

class _Aviso extends StatelessWidget {
  const _Aviso({required this.mensagem, required this.aoTentarNovamente});

  final String mensagem;
  final Future<void> Function() aoTentarNovamente;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 48),
            const SizedBox(height: 12),
            Text(mensagem, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: aoTentarNovamente,
              child: const Text('Tentar novamente'),
            ),
          ],
        ),
      ),
    );
  }
}
