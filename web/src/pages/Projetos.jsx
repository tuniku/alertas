import { useEffect, useState } from 'react';
import api from '../api';

const FORM_VAZIO = { nome: '' };

export default function Projetos() {
  const [projetos, setProjetos] = useState([]);
  const [form, setForm] = useState(FORM_VAZIO);
  const [editandoId, setEditandoId] = useState(null);
  const [erro, setErro] = useState('');

  async function carregar() {
    const { data } = await api.get('/projetos');
    setProjetos(data);
  }

  useEffect(() => {
    carregar();
  }, []);

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    try {
      if (editandoId) {
        await api.put(`/projetos/${editandoId}`, form);
      } else {
        await api.post('/projetos', form);
      }
      setForm(FORM_VAZIO);
      setEditandoId(null);
      carregar();
    } catch (err) {
      setErro(err.response?.data?.message || 'Erro ao salvar projeto.');
    }
  }

  async function excluir(p) {
    if (!confirm(`Excluir o projeto "${p.nome}"? Os alertas dele também serão excluídos.`)) return;
    await api.delete(`/projetos/${p.id}`);
    carregar();
  }

  return (
    <div>
      <h2>Projetos</h2>

      <form className="form-inline" onSubmit={salvar}>
        <input
          placeholder="Nome do projeto"
          value={form.nome}
          onChange={(e) => setForm({ nome: e.target.value })}
          required
        />
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
      </form>
      {erro && <div className="alerta-erro">{erro}</div>}

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Alertas</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {projetos.map((p) => (
            <tr key={p.id}>
              <td>{p.id}</td>
              <td>{p.nome}</td>
              <td>{p.alertas_count}</td>
              <td className="acoes">
                <button
                  className="secundario"
                  onClick={() => {
                    setEditandoId(p.id);
                    setForm({ nome: p.nome });
                  }}
                >
                  Editar
                </button>
                <button className="perigo" onClick={() => excluir(p)}>
                  Excluir
                </button>
              </td>
            </tr>
          ))}
          {projetos.length === 0 && (
            <tr>
              <td colSpan="4" className="vazio">Nenhum projeto cadastrado.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
