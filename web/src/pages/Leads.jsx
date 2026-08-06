import { useEffect, useState } from 'react';
import api from '../api';

function formatar(dt) {
  return dt ? new Date(dt).toLocaleString('pt-BR') : '—';
}

function moeda(valor, sigla) {
  if (valor === null || valor === undefined) return '—';
  const simbolos = { BRL: 'R$', USD: 'US$', EUR: '€' };
  const numero = Number(valor).toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `${simbolos[sigla] || sigla || ''} ${numero}`.trim();
}

export default function Leads() {
  const [pagina, setPagina] = useState(null);
  const [origens, setOrigens] = useState([]);
  const [origem, setOrigem] = useState('');
  const [busca, setBusca] = useState('');
  const [detalhe, setDetalhe] = useState(null);

  useEffect(() => {
    api.get('/leads/origens').then(({ data }) => setOrigens(data));
  }, []);

  async function carregar(page = 1) {
    const params = { page };
    if (origem) params.origem = origem;
    if (busca.trim()) params.busca = busca.trim();
    const { data } = await api.get('/leads', { params });
    setPagina(data);
  }

  // A busca só dispara no submit (e não a cada tecla) para não gerar uma
  // requisição por caractere digitado.
  useEffect(() => {
    carregar();
  }, [origem]);

  const itens = pagina?.data || [];

  return (
    <div>
      <div className="cabecalho-pagina">
        <h2>Leads</h2>
        <form
          className="filtros"
          onSubmit={(e) => {
            e.preventDefault();
            carregar();
          }}
        >
          <input
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Nome, e-mail, telefone, empresa..."
          />
          <select value={origem} onChange={(e) => setOrigem(e.target.value)}>
            <option value="">Todas as origens</option>
            {origens.map((o) => (
              <option key={o} value={o}>{o}</option>
            ))}
          </select>
          <button type="submit" className="secundario">Buscar</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>Recebido em</th>
            <th>Título</th>
            <th>Contato</th>
            <th>Empresa</th>
            <th>Valor</th>
            <th>Origem</th>
            <th>Etapa</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {itens.map((lead) => (
            <tr key={lead.id}>
              <td>{formatar(lead.recebido_em)}</td>
              <td>{lead.titulo || '—'}</td>
              <td>
                {lead.pessoa_nome || '—'}
                {lead.pessoa_email && <small className="sub">{lead.pessoa_email}</small>}
                {lead.pessoa_telefone && <small className="sub">{lead.pessoa_telefone}</small>}
              </td>
              <td>{lead.organizacao_nome || '—'}</td>
              <td>{moeda(lead.valor, lead.moeda)}</td>
              <td>{lead.origem || '—'}</td>
              <td>{lead.stage_nome || '—'}</td>
              <td className="acoes">
                <button className="secundario" onClick={() => setDetalhe(lead)}>
                  Detalhes
                </button>
                {lead.url && (
                  <a className="botao-link" href={lead.url} target="_blank" rel="noreferrer">
                    Abrir no CRM
                  </a>
                )}
              </td>
            </tr>
          ))}
          {itens.length === 0 && (
            <tr>
              <td colSpan="8" className="vazio">Nenhum lead recebido ainda.</td>
            </tr>
          )}
        </tbody>
      </table>

      {pagina && pagina.last_page > 1 && (
        <div className="paginacao">
          <button
            className="secundario"
            disabled={pagina.current_page <= 1}
            onClick={() => carregar(pagina.current_page - 1)}
          >
            Anterior
          </button>
          <span>Página {pagina.current_page} de {pagina.last_page}</span>
          <button
            className="secundario"
            disabled={pagina.current_page >= pagina.last_page}
            onClick={() => carregar(pagina.current_page + 1)}
          >
            Próxima
          </button>
        </div>
      )}

      {detalhe && (
        <div className="modal-fundo" onClick={() => setDetalhe(null)}>
          {/* stopPropagation: clicar dentro do painel não deve fechá-lo. */}
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-topo">
              <h3>{detalhe.titulo || 'Lead'}</h3>
              <button className="secundario" onClick={() => setDetalhe(null)}>Fechar</button>
            </div>
            <dl className="detalhes">
              <dt>Negócio</dt><dd>#{detalhe.numero ?? '—'} · {detalhe.deal_id}</dd>
              <dt>Status</dt><dd>{detalhe.status || '—'}</dd>
              <dt>Funil / Etapa</dt><dd>{detalhe.pipeline_nome || '—'} / {detalhe.stage_nome || '—'}</dd>
              <dt>Responsável</dt><dd>{detalhe.owner_nome || '—'} {detalhe.owner_email ? `(${detalhe.owner_email})` : ''}</dd>
              <dt>Tags</dt><dd>{detalhe.tags?.length ? detalhe.tags.join(', ') : '—'}</dd>
              <dt>Criado na origem</dt><dd>{formatar(detalhe.criado_em_origem)}</dd>
              <dt>Evento</dt><dd>{detalhe.evento} · {detalhe.evento_id}</dd>
            </dl>
            <details>
              <summary>Payload completo recebido</summary>
              <pre>{JSON.stringify(detalhe.payload, null, 2)}</pre>
            </details>
          </div>
        </div>
      )}
    </div>
  );
}
