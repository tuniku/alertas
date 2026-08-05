import { useEffect, useState } from 'react';
import api from '../api';

const FORM_VAZIO = { nome: '', driver: '', ativo: true, configuracao: {} };

// Rótulos amigáveis para os campos de configuração que os drivers expõem.
// Um driver novo sem entrada aqui simplesmente mostra o nome bruto do campo.
const ROTULOS_CAMPO = {
  webhook_url: 'URL do webhook',
  bot_token: 'Token do bot',
  chat_id: 'ID do chat',
};

const AJUDA_CAMPO = {
  webhook_url:
    'No Discord: Configurações do canal → Integrações → Webhooks → Copiar URL do webhook.',
  bot_token:
    'No Telegram: converse com o @BotFather, envie /newbot e copie o token devolvido.',
  chat_id:
    'Adicione o bot ao grupo/canal (como admin, se for canal), envie uma mensagem lá e abra ' +
    'https://api.telegram.org/bot<TOKEN>/getUpdates para ver o chat.id. Grupos e canais têm id negativo.',
};

// Placeholder por campo, com o mesmo critério dos rótulos: um campo sem
// entrada aqui simplesmente não mostra exemplo.
const PLACEHOLDER_CAMPO = {
  webhook_url: 'https://discord.com/api/webhooks/...',
  bot_token: '123456789:AAH...',
  chat_id: '-1001234567890',
};

export default function TiposDisparo() {
  const [tipos, setTipos] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [form, setForm] = useState(FORM_VAZIO);
  const [editandoId, setEditandoId] = useState(null);
  const [erro, setErro] = useState('');
  const [aviso, setAviso] = useState('');

  async function carregar() {
    const [t, d] = await Promise.all([
      api.get('/tipos-disparo'),
      api.get('/tipos-disparo/drivers'),
    ]);
    setTipos(t.data);
    setDrivers(d.data);
    // Pré-seleciona o primeiro driver disponível em formulário novo.
    setForm((atual) =>
      atual.driver ? atual : { ...atual, driver: d.data[0]?.driver || '' }
    );
  }

  useEffect(() => {
    carregar();
  }, []);

  const driverAtual = drivers.find((d) => d.driver === form.driver);

  function limpar() {
    setEditandoId(null);
    setForm({ ...FORM_VAZIO, driver: drivers[0]?.driver || '' });
  }

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    setAviso('');
    try {
      if (editandoId) {
        await api.put(`/tipos-disparo/${editandoId}`, form);
      } else {
        await api.post('/tipos-disparo', form);
      }
      limpar();
      carregar();
    } catch (err) {
      const detalhes = err.response?.data?.errors;
      setErro(
        detalhes
          ? Object.values(detalhes).flat().join(' ')
          : 'Erro ao salvar tipo de disparo.'
      );
    }
  }

  async function excluir(t) {
    if (!confirm(`Excluir o tipo de disparo "${t.nome}"?`)) return;
    await api.delete(`/tipos-disparo/${t.id}`);
    carregar();
  }

  async function testar(t) {
    setErro('');
    setAviso('');
    try {
      const { data } = await api.post(`/tipos-disparo/${t.id}/testar`);
      setAviso(data.mensagem);
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Falha no teste.');
    }
  }

  function editar(t) {
    setEditandoId(t.id);
    setForm({
      nome: t.nome,
      driver: t.driver,
      ativo: t.ativo,
      configuracao: t.configuracao || {},
    });
  }

  return (
    <div>
      <h2>Tipos de disparo</h2>
      <p className="muted-left">
        Cada tipo é um destino configurado (um canal do Discord, um chat do
        Telegram e, futuramente, e-mail ou uma lâmpada). Um alerta pode usar
        vários ao mesmo tempo.
      </p>

      <form className="form-grid" onSubmit={salvar}>
        <label>
          Nome
          <input
            value={form.nome}
            onChange={(e) => setForm({ ...form, nome: e.target.value })}
            placeholder="ex.: Discord #alertas-carbel, Telegram Suporte"
            required
          />
        </label>
        <label>
          Canal
          <select
            value={form.driver}
            onChange={(e) =>
              // Troca de driver zera a configuração: os campos de um
              // driver não fazem sentido para outro.
              setForm({ ...form, driver: e.target.value, configuracao: {} })
            }
            required
          >
            {drivers.map((d) => (
              <option key={d.driver} value={d.driver}>
                {d.rotulo}
              </option>
            ))}
          </select>
        </label>
        <label className="checkbox-campo">
          <span>Ativo</span>
          <input
            type="checkbox"
            checked={form.ativo}
            onChange={(e) => setForm({ ...form, ativo: e.target.checked })}
          />
        </label>

        {/* Campos de configuração montados a partir do que a API informa
            sobre o driver selecionado — nenhuma alteração aqui é
            necessária quando um driver novo é adicionado no backend. */}
        {driverAtual?.campos.map((campo) => (
          <label key={campo} className="campo-largo">
            {ROTULOS_CAMPO[campo] || campo}
            <input
              value={form.configuracao[campo] || ''}
              onChange={(e) =>
                setForm({
                  ...form,
                  configuracao: { ...form.configuracao, [campo]: e.target.value },
                })
              }
              placeholder={PLACEHOLDER_CAMPO[campo] || ''}
              required
            />
            {AJUDA_CAMPO[campo] && <small>{AJUDA_CAMPO[campo]}</small>}
          </label>
        ))}

        <div className="form-acoes">
          <button type="submit">{editandoId ? 'Salvar alteração' : 'Adicionar'}</button>
          {editandoId && (
            <button type="button" className="secundario" onClick={limpar}>
              Cancelar
            </button>
          )}
        </div>
      </form>

      {erro && <div className="alerta-erro">{erro}</div>}
      {aviso && <div className="alerta-sucesso">{aviso}</div>}

      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Canal</th>
            <th>Alertas usando</th>
            <th>Situação</th>
            <th className="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          {tipos.map((t) => (
            <tr key={t.id}>
              <td>{t.nome}</td>
              <td>{drivers.find((d) => d.driver === t.driver)?.rotulo || t.driver}</td>
              <td>{t.alertas_count}</td>
              <td>
                {t.ativo ? (
                  <span className="badge imp-baixa">Ativo</span>
                ) : (
                  <span className="badge fechado">Inativo</span>
                )}
              </td>
              <td className="acoes">
                <button className="secundario" onClick={() => testar(t)}>Testar</button>
                <button className="secundario" onClick={() => editar(t)}>Editar</button>
                <button className="perigo" onClick={() => excluir(t)}>Excluir</button>
              </td>
            </tr>
          ))}
          {tipos.length === 0 && (
            <tr>
              <td colSpan="5" className="vazio">Nenhum tipo de disparo cadastrado.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
