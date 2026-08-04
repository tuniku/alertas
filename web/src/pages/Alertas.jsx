import { useEffect, useState } from 'react';
import api from '../api';

const FORM_VAZIO = {
  projeto_id: '',
  codigo: '',
  nome: '',
  importancia: 5,
  tipo_disparo_id: '',
  expiracao_minutos: '',
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

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    const payload = {
      ...form,
      tipo_disparo_id: form.tipo_disparo_id || null,
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
      tipo_disparo_id: a.tipo_disparo_id || '',
      expiracao_minutos: a.expiracao_minutos || '',
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
          Tipo de disparo
          <select {...campo('tipo_disparo_id')}>
            <option value="">(definir depois)</option>
            {tipos.map((t) => (
              <option key={t.id} value={t.id}>{t.nome}</option>
            ))}
          </select>
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
              <td className="acoes">
                <button className="secundario" onClick={() => editar(a)}>Editar</button>
                <button className="perigo" onClick={() => excluir(a)}>Excluir</button>
              </td>
            </tr>
          ))}
          {alertas.length === 0 && (
            <tr>
              <td colSpan="6" className="vazio">Nenhum alerta cadastrado.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
