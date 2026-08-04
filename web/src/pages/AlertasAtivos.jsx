import { useEffect, useState } from 'react';
import api from '../api';

function formatar(dt) {
  return dt ? new Date(dt).toLocaleString('pt-BR') : '—';
}

export default function AlertasAtivos() {
  const [pagina, setPagina] = useState(null);
  const [somenteAtivos, setSomenteAtivos] = useState(true);
  const [erro, setErro] = useState('');

  async function carregar(page = 1) {
    setErro('');
    try {
      const { data } = await api.get('/alertas-ativos', {
        params: { page, somente_ativos: somenteAtivos ? '1' : '0' },
      });
      setPagina(data);
    } catch {
      setErro('Erro ao carregar alertas ativos.');
    }
  }

  useEffect(() => {
    carregar();
  }, [somenteAtivos]);

  async function fechar(item) {
    if (!confirm(`Fechar o alerta "${item.alerta?.nome}"?`)) return;
    try {
      await api.post(`/alertas-ativos/${item.id}/fechar`);
      carregar(pagina?.current_page || 1);
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Erro ao fechar alerta.');
    }
  }

  const itens = pagina?.data || [];

  return (
    <div>
      <div className="cabecalho-pagina">
        <h2>Alertas ativos</h2>
        <label className="checkbox">
          <input
            type="checkbox"
            checked={somenteAtivos}
            onChange={(e) => setSomenteAtivos(e.target.checked)}
          />
          Somente ativos
        </label>
      </div>
      {erro && <div className="alerta-erro">{erro}</div>}

      <table>
        <thead>
          <tr>
            <th>Projeto</th>
            <th>Alerta</th>
            <th>Importância</th>
            <th>Criado em</th>
            <th>Última ocorrência</th>
            <th>Expira em</th>
            <th>Situação</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {itens.map((item) => (
            <tr key={item.id}>
              <td>{item.alerta?.projeto?.nome}</td>
              <td>{item.alerta?.nome}</td>
              <td>
                <span className={`badge imp-${item.alerta?.importancia >= 8 ? 'alta' : item.alerta?.importancia >= 4 ? 'media' : 'baixa'}`}>
                  {item.alerta?.importancia}
                </span>
              </td>
              <td>{formatar(item.created_at)}</td>
              <td>{formatar(item.updated_at)}</td>
              <td>{formatar(item.expira_em)}</td>
              <td>
                {item.ativo ? (
                  <span className="badge ativo">Ativo</span>
                ) : (
                  <span className="badge fechado">
                    {/* A relação fechadoPor é serializada como "fechado_por":
                        objeto do usuário quando fechado manualmente, null quando
                        foi encerrado pelo sistema (expiração). */}
                    {item.fechado_por?.name
                      ? `Fechado por ${item.fechado_por.name}`
                      : 'Fechado (sistema)'}
                  </span>
                )}
              </td>
              <td className="acoes">
                {item.ativo && (
                  <button className="perigo" onClick={() => fechar(item)}>Fechar</button>
                )}
              </td>
            </tr>
          ))}
          {itens.length === 0 && (
            <tr>
              <td colSpan="8" className="vazio">Nenhum alerta ativo no momento.</td>
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
