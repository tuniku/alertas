import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import api from '../api';

export default function Layout() {
  const navigate = useNavigate();
  const usuario = JSON.parse(localStorage.getItem('usuario') || 'null');

  async function sair() {
    try {
      await api.post('/logout');
    } catch {
      // token já pode estar inválido; segue o fluxo de saída mesmo assim
    }
    localStorage.removeItem('token');
    localStorage.removeItem('usuario');
    navigate('/login');
  }

  return (
    <div className="layout">
      <aside className="sidebar">
        <h1>Alertas</h1>
        <nav>
          <NavLink to="/ativos">Alertas ativos</NavLink>
          <NavLink to="/logs">Histórico (log)</NavLink>
          <NavLink to="/projetos">Projetos</NavLink>
          <NavLink to="/alertas">Alertas</NavLink>
          <NavLink to="/usuarios">Usuários</NavLink>
        </nav>
        <div className="sidebar-footer">
          {usuario && <span>{usuario.name}</span>}
          <button onClick={sair}>Sair</button>
        </div>
      </aside>
      <main className="conteudo">
        <Outlet />
      </main>
    </div>
  );
}
