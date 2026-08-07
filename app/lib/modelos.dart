/// Modelos que espelham o que os endpoints `/api/app/*` devolvem.
///
/// Os campos vêm já achatados pelo `AppController` (projeto e alerta
/// como texto, em vez de objetos aninhados), então a conversão aqui é
/// direta e não precisa navegar em relações.
library;

class AlertaAtivo {
  const AlertaAtivo({
    required this.id,
    required this.projeto,
    required this.alerta,
    required this.codigo,
    required this.importancia,
    required this.nivel,
    required this.criadoEm,
    required this.atualizadoEm,
    this.expiraEm,
  });

  final int id;
  final String projeto;
  final String alerta;
  final String codigo;
  final int importancia;
  final String nivel;
  final DateTime criadoEm;
  final DateTime atualizadoEm;
  final DateTime? expiraEm;

  factory AlertaAtivo.doJson(Map<String, dynamic> json) => AlertaAtivo(
        id: json['id'] as int,
        projeto: (json['projeto'] ?? '—') as String,
        alerta: (json['alerta'] ?? '—') as String,
        codigo: (json['codigo'] ?? '') as String,
        importancia: (json['importancia'] ?? 0) as int,
        nivel: (json['nivel'] ?? 'Informativo') as String,
        criadoEm: DateTime.parse(json['criado_em'] as String).toLocal(),
        atualizadoEm: DateTime.parse(json['atualizado_em'] as String).toLocal(),
        expiraEm: json['expira_em'] == null
            ? null
            : DateTime.parse(json['expira_em'] as String).toLocal(),
      );
}

class AlertaLog {
  const AlertaLog({
    required this.id,
    required this.projeto,
    required this.alerta,
    required this.importancia,
    required this.recebidoEm,
    this.descricao,
    this.eventoEm,
  });

  final int id;
  final String projeto;
  final String alerta;
  final int importancia;
  final DateTime recebidoEm;
  final String? descricao;
  final DateTime? eventoEm;

  factory AlertaLog.doJson(Map<String, dynamic> json) => AlertaLog(
        id: json['id'] as int,
        projeto: (json['projeto'] ?? '—') as String,
        alerta: (json['alerta'] ?? '—') as String,
        importancia: (json['importancia'] ?? 0) as int,
        descricao: json['descricao'] as String?,
        recebidoEm: DateTime.parse(json['recebido_em'] as String).toLocal(),
        eventoEm: json['evento_em'] == null
            ? null
            : DateTime.parse(json['evento_em'] as String).toLocal(),
      );
}

/// Erro vindo da API, com a mensagem já pronta para exibir.
class ErroApi implements Exception {
  const ErroApi(this.mensagem, {this.status});

  final String mensagem;
  final int? status;

  /// Sessão expirada ou token revogado — o app precisa voltar ao login.
  bool get naoAutenticado => status == 401;

  @override
  String toString() => mensagem;
}
