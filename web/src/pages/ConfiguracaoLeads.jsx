import { useEffect, useState } from 'react';
import api from '../api';

export default function ConfiguracaoLeads() {
  const [config, setConfig] = useState(null);
  const [form, setForm] = useState({ discord_webhook: '', token: '' });
  const [erro, setErro] = useState('');
  const [aviso, setAviso] = useState('');

  async function carregar() {
    const { data } = await api.get('/configuracoes/leads');
    setConfig(data);
    setForm({
      discord_webhook: data.discord_webhook || '',
      token: data.token || '',
    });
  }

  useEffect(() => {
    carregar();
  }, []);

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    setAviso('');
    try {
      const { data } = await api.put('/configuracoes/leads', {
        discord_webhook: form.discord_webhook || null,
        token: form.token || null,
      });
      setConfig(data);
      setAviso('Configuração salva.');
    } catch (err) {
      const detalhes = err.response?.data?.errors;
      setErro(
        detalhes
          ? Object.values(detalhes).flat().join(' ')
          : 'Erro ao salvar a configuração.'
      );
    }
  }

  async function gerarToken() {
    setErro('');
    setAviso('');
    const { data } = await api.post('/configuracoes/leads/token');
    setForm((f) => ({ ...f, token: data.token }));
    setAviso('Token novo gerado e salvo. Atualize a URL no FunnelsFlow.');
  }

  async function testar() {
    setErro('');
    setAviso('');
    try {
      const { data } = await api.post('/configuracoes/leads/testar');
      setAviso(data.mensagem);
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Falha no teste.');
    }
  }

  const urlCompleta =
    config?.url_webhook && form.token
      ? `${config.url_webhook}?token=${form.token}`
      : null;

  return (
    <div>
      <h2>Configuração de leads</h2>
      <p className="muted-left">
        Recebe os leads do FunnelsFlow pelo webhook de saída e avisa em um canal
        do Discord. Só o evento <code>deal.created</code> é tratado.
      </p>

      <form className="form-grid" onSubmit={salvar}>
        <label className="campo-largo">
          Webhook do Discord (canal dos leads)
          <input
            value={form.discord_webhook}
            onChange={(e) => setForm({ ...form, discord_webhook: e.target.value })}
            placeholder="https://discord.com/api/webhooks/..."
          />
          <small>
            No Discord: Configurações do canal → Integrações → Webhooks → Copiar
            URL do webhook. Use um canal dedicado a leads, separado do de alertas.
          </small>
        </label>

        <label className="campo-largo">
          Token do endpoint
          <input
            type="password"
            value={form.token}
            onChange={(e) => setForm({ ...form, token: e.target.value })}
            placeholder="mínimo 16 caracteres"
          />
          <small>
            Protege o endpoint público. Sem token configurado, o endpoint recusa
            tudo — é proposital, para um cadastro incompleto não virar uma porta
            aberta.
          </small>
        </label>

        <div className="form-acoes">
          <button type="submit">Salvar</button>
          <button type="button" className="secundario" onClick={gerarToken}>
            Gerar token novo
          </button>
          <button type="button" className="secundario" onClick={testar}>
            Testar canal
          </button>
        </div>
      </form>

      {erro && <div className="alerta-erro">{erro}</div>}
      {aviso && <div className="alerta-sucesso">{aviso}</div>}

      <h3>URL para configurar no FunnelsFlow</h3>
      <p className="muted-left">
        Em <strong>Configurações → Integrações → Webhooks de saída</strong>, crie
        um webhook apontando para o endereço abaixo e assine apenas o evento{' '}
        <code>deal.created</code>.
      </p>

      {urlCompleta ? (
        <div className="bloco-copiavel">
          <code>{urlCompleta}</code>
          <button
            className="secundario"
            onClick={() => {
              navigator.clipboard.writeText(urlCompleta);
              setAviso('URL copiada.');
            }}
          >
            Copiar
          </button>
        </div>
      ) : (
        <div className="alerta-erro">
          Gere ou informe um token para que a URL seja montada.
        </div>
      )}

      <p className="muted-left">
        Se o FunnelsFlow permitir cabeçalhos personalizados, prefira enviar o
        token no header <code>X-Webhook-Token</code> e usar a URL sem o{' '}
        <code>?token=</code> — o endpoint aceita as duas formas, e o header não
        aparece em logs de servidor como a query string aparece.
      </p>
    </div>
  );
}
