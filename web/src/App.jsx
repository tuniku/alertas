import { Navigate, Route, Routes } from 'react-router-dom';
import Layout from './components/Layout';
import Login from './pages/Login';
import AlertasAtivos from './pages/AlertasAtivos';
import Logs from './pages/Logs';
import Projetos from './pages/Projetos';
import Alertas from './pages/Alertas';
import TiposDisparo from './pages/TiposDisparo';
import Leads from './pages/Leads';
import ConfiguracaoLeads from './pages/ConfiguracaoLeads';
import ConfiguracaoPush from './pages/ConfiguracaoPush';
import Usuarios from './pages/Usuarios';

function RequireAuth({ children }) {
  return localStorage.getItem('token') ? children : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <RequireAuth>
            <Layout />
          </RequireAuth>
        }
      >
        <Route index element={<Navigate to="/ativos" replace />} />
        <Route path="ativos" element={<AlertasAtivos />} />
        <Route path="logs" element={<Logs />} />
        <Route path="projetos" element={<Projetos />} />
        <Route path="alertas" element={<Alertas />} />
        <Route path="tipos-disparo" element={<TiposDisparo />} />
        <Route path="leads" element={<Leads />} />
        <Route path="configuracao-leads" element={<ConfiguracaoLeads />} />
        <Route path="configuracao-push" element={<ConfiguracaoPush />} />
        <Route path="usuarios" element={<Usuarios />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
