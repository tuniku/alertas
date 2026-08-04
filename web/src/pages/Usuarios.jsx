import { useEffect, useState } from 'react';
import api from '../api';

const FORM_VAZIO = { name: '', email: '', password: '' };

export default function Usuarios() {
  const [usuarios, setUsuarios] = useState([]);
  const [form, setForm] = useState(FORM_VAZIO);
  const [editandoId, setEditandoId] = useState(null);
  const [erro, setErro] = useState('');

  async function carregar() {
    const { data } = await api.get('/usuarios');
    setUsuarios(data);
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
    try {
      if (editandoId) {
        const payload = { ...form };
        if (!payload.password) delete payload.password;
        await api.put(`/usuarios/${editandoId}`, payload);
      } else {
        await api.post('/usuarios', form);
      }
      setForm(FORM_VAZIO);
      setEditandoId(null);
      carregar();
    } catch (err) {
      const detalhes = err.response?.data?.errors;
      setErro(
        detalhes ? Object.values(detalhes).flat().join(' ') : 'Erro ao salvar usuário.'
      );
    }
  }

  async function excluir(u) {
    if (!confirm(`Excluir o usuário "${u.name}"?`)) return;
    try {
      await api.delete(`/usuarios/${u.id}`);
      carregar();
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Erro ao excluir usuário.');
    }
  }

  return (
    <div>
      <h2>Usuários</h2>

      <form className="form-grid" onSubmit={salvar}>
        <label>
          Nome
          <input {...campo('name')} required />
        </label>
        <label>
          E-mail
          <input type="email" {...campo('email')} required />
        </label>
        <label>
          Senha {editandoId && <small>(vazio = manter atual)</small>}
          <input
            type="password"
            {...campo('password')}
            minLength="8"
            required={!editandoId}
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
            <th>ID</th>
            <th>Nome</th>
            <th>E-mail</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {usuarios.map((u) => (
            <tr key={u.id}>
              <td>{u.id}</td>
              <td>{u.name}</td>
              <td>{u.email}</td>
              <td className="acoes">
                <button
                  className="secundario"
                  onClick={() => {
                    setEditandoId(u.id);
                    setForm({ name: u.name, email: u.email, password: '' });
                  }}
                >
                  Editar
                </button>
                <button className="perigo" onClick={() => excluir(u)}>Excluir</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
