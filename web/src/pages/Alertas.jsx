import { useEffect, useState } from 'react';
import api from '../api';

const FORM_VAZIO = {
  projeto_id: '',
  codigo: '',
  nome: '',
  importancia: 5,
  expiracao_minutos: '',
  disponivel_app: false,
  tipos_disparo: [],
};

export default function Alertas() {
  const [alertas, setAlertas] = useState([]);
  const [projetos, setProjetos] = useState([]);
  const [tipos, setTipos] = useState([]);
  const [form, setForm] = useState(FORM_VAZIO);
  const [editandoId, setEditandoId] = useState(null);
  const [erro, setErro] = useState('');

  async function carregar() {
    const [a, p, t] = await Promise.all([
      api.get('/alertas'),
      api.get('/projetos'),
      api.get('/tipos-disparo'),
    ]);
    setAlertas(a.data);
    setProjetos(p.data);
    setTipos(t.data);
  }

  useEffect(() => {
    carregar();
  }, []);

  function campo(nome) {
    return {
      value: form[nome],
      onChange: (e) => setForm({ ...form, [nome]: e.target.value }),
    };
  }

  function alternarCanal(id) {
    setForm((atual) => ({
      ...atual,
      tipos_disparo: atual.tipos_disparo.includes(id)
        ? atual.tipos_disparo.filter((x) => x !== id)
        : [...atual.tipos_disparo, id],
    }));
  }

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    const payload = {
      ...form,
      expiracao_minutos: form.expiracao_minutos || null,
    };
    try {
      if (editandoId) {
        await api.put(`/alertas/${editandoId}`, payload);
      } else {
        await api.post('/alertas', payload);
      }
      setForm(FORM_VAZIO);
      setEditandoId(null);
      carregar();
    } catch (err) {
      const detalhes = err.response?.data?.errors;
      setErro(
        detalhes ? Object.values(detalhes).flat().join(' ') : 'Erro ao salvar alerta.'
      );
    }
  }

  async function excluir(a) {
    if (!confirm(`Excluir o alerta "${a.nome}"?`)) return;
    await api.delete(`/alertas/${a.id}`);
    carregar();
  }

  function editar(a) {
    setEditandoId(a.id);
    setForm({
      projeto_id: a.projeto_id,
      codigo: a.codigo,
      nome: a.nome,
      importancia: a.importancia,
      expiracao_minutos: a.expiracao_minutos || '',
      disponivel_app: !!a.disponivel_app,
      tipos_disparo: (a.tipos_disparo || []).map((t) => t.id),
    });
  }

  return (
    <div>
      <h2>Alertas</h2>

      <form className="form-grid" onSubmit={salvar}>
        <label>
          Projeto
          <select {...campo('projeto_id')} required>
            <option value="">Selecione...</option>
            {projetos.map((p) => (
              <option key={p.id} value={p.id}>{p.nome}</option>
            ))}
          </select>
        </label>
        <label>
          Código (usado no disparo)
          <input {...campo('codigo')} placeholder="ex.: backup-falhou" required />
        </label>
        <label>
          Nome
          <input {...campo('nome')} placeholder="ex.: Falha no backup noturno" required />
        </label>
        <label>
          Importância (0 a 10)
          <input type="number" min="0" max="10" {...campo('importancia')} required />
        </label>
        <label>
          Expiração da deduplicação (min)
          <input
            type="number"
            min="1"
            {...campo('expiracao_minutos')}
            placeholder="vazio = nunca expira"
          />
        </label>

        <label className="checkbox-campo">
          <span>Disponível no aplicativo</span>
          <input
            type="checkbox"
            checked={form.disponivel_app}
            onChange={(e) => setForm({ ...form, disponivel_app: e.target.checked })}
          />
        </label>

        {/* Vários canais por alerta: um alerta crítico pode postar no
            Discord e, futuramente, acender a lâmpada ao mesmo tempo. */}
        <div className="campo-largo">
          <span className="rotulo">Notificar em</span>
          {tipos.length === 0 ? (
            <small className="muted">
              Nenhum tipo de disparo cadastrado ainda — cadastre um em "Tipos de disparo".
            </small>
          ) : (
            <div className="lista-checkbox">
              {tipos.map((t) => (
                <label key={t.id} className="checkbox">
                  <input
                    type="checkbox"
                    checked={form.tipos_disparo.includes(t.id)}
                    onChange={() => alternarCanal(t.id)}
                  />
                  {t.nome}
                  {!t.ativo && <span className="badge fechado">inativo</span>}
                </label>
              ))}
            </div>
          )}
        </div>

        <div className="form-acoes">
          <button type="submit">{editandoId ? 'Salvar alteração' : 'Adicionar'}</button>
          {editandoId && (
            <button
              type="button"
              className="secundario"
              onClick={() => {
                setEditandoId(null);
                setForm(FORM_VAZIO);
              }}
            >
              Cancelar
            </button>
          )}
        </div>
      </form>
      {erro && <div className="alerta-erro">{erro}</div>}

      <table>
        <thead>
          <tr>
            <th>Projeto</th>
            <th>Código</th>
            <th>Nome</th>
            <th>Importância</th>
            <th>Expiração (min)</th>
            <th>App</th>
            <th>Notifica em</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {alertas.map((a) => (
            <tr key={a.id}>
              <td>{a.projeto?.nome}</td>
              <td><code>{a.codigo}</code></td>
              <td>{a.nome}</td>
              <td>
                <span className={`badge imp-${a.importancia >= 8 ? 'alta' : a.importancia >= 4 ? 'media' : 'baixa'}`}>
                  {a.importancia}
                </span>
              </td>
              <td>{a.expiracao_minutos ?? '—'}</td>
              <td>
                {a.disponivel_app
                  ? <span className="badge imp-baixa">sim</span>
                  : <span className="badge fechado">não</span>}
              </td>
              <td>
                {a.tipos_disparo?.length
                  ? a.tipos_disparo.map((t) => t.nome).join(', ')
                  : '—'}
              </td>
              <td className="acoes">
                <button className="secundario" onClick={() => editar(a)}>Editar</button>
                <button className="perigo" onClick={() => excluir(a)}>Excluir</button>
              </td>
            </tr>
          ))}
          {alertas.length === 0 && (
            <tr>
              <td colSpan="8" className="vazio">Nenhum alerta cadastrado.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
