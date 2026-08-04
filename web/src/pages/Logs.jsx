import { useEffect, useState } from 'react';
import api from '../api';

function formatar(dt) {
  return dt ? new Date(dt).toLocaleString('pt-BR') : '—';
}

export default function Logs() {
  const [pagina, setPagina] = useState(null);
  const [projetos, setProjetos] = useState([]);
  const [projetoId, setProjetoId] = useState('');

  useEffect(() => {
    api.get('/projetos').then(({ data }) => setProjetos(data));
  }, []);

  async function carregar(page = 1) {
    const params = { page };
    if (projetoId) params.projeto_id = projetoId;
    const { data } = await api.get('/logs', { params });
    setPagina(data);
  }

  useEffect(() => {
    carregar();
  }, [projetoId]);

  const itens = pagina?.data || [];

  return (
    <div>
      <div className="cabecalho-pagina">
        <h2>Histórico de eventos</h2>
        <select value={projetoId} onChange={(e) => setProjetoId(e.target.value)}>
          <option value="">Todos os projetos</option>
          {projetos.map((p) => (
            <option key={p.id} value={p.id}>{p.nome}</option>
          ))}
        </select>
      </div>

      <table>
        <thead>
          <tr>
            <th>Recebido em</th>
            <th>Evento em</th>
            <th>Projeto</th>
            <th>Alerta</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          {itens.map((log) => (
            <tr key={log.id}>
              <td>{formatar(log.recebido_em)}</td>
              <td>{formatar(log.evento_em)}</td>
              <td>{log.alerta?.projeto?.nome}</td>
              <td>{log.alerta?.nome}</td>
              <td className="descricao">{log.descricao || '—'}</td>
            </tr>
          ))}
          {itens.length === 0 && (
            <tr>
              <td colSpan="5" className="vazio">Nenhum evento registrado.</td>
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
    </div>
  );
}
