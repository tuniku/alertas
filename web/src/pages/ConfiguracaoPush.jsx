import { useEffect, useState } from 'react';
import api from '../api';

export default function ConfiguracaoPush() {
  const [config, setConfig] = useState(null);
  const [jsonColado, setJsonColado] = useState('');
  const [erro, setErro] = useState('');
  const [aviso, setAviso] = useState('');
  const [salvando, setSalvando] = useState(false);
  const [testando, setTestando] = useState(false);

  async function carregar() {
    const { data } = await api.get('/configuracoes/push');
    setConfig(data);
  }

  useEffect(() => {
    carregar();
  }, []);

  async function salvar(e) {
    e.preventDefault();
    setErro('');
    setAviso('');
    setSalvando(true);
    try {
      const { data } = await api.put('/configuracoes/push', {
        service_account_json: jsonColado || null,
      });
      setConfig(data);
      setJsonColado('');
      setAviso('Credencial salva.');
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Erro ao salvar a credencial.');
    } finally {
      setSalvando(false);
    }
  }

  async function remover() {
    setErro('');
    setAviso('');
    setSalvando(true);
    try {
      const { data } = await api.put('/configuracoes/push', {
        service_account_json: null,
      });
      setConfig(data);
      setAviso('Credencial removida.');
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Erro ao remover a credencial.');
    } finally {
      setSalvando(false);
    }
  }

  async function testar() {
    setErro('');
    setAviso('');
    setTestando(true);
    try {
      const { data } = await api.post('/configuracoes/push/testar');
      setAviso(data.mensagem);
    } catch (err) {
      setErro(err.response?.data?.mensagem || 'Falha no teste.');
    } finally {
      setTestando(false);
    }
  }

  return (
    <div>
      <h2>Configuração de push</h2>
      <p className="muted-left">
        Credencial que o servidor usa para enviar notificações push ao
        aplicativo Android, via Firebase Cloud Messaging. Todo alerta marcado
        como <strong>"Disponível no aplicativo"</strong> dispara um push para
        todos os aparelhos com algum usuário logado — não é um canal
        selecionável por alerta, como Discord ou Telegram.
      </p>

      {config && (
        <div className={config.configurado ? 'alerta-sucesso' : 'alerta-erro'}>
          {config.configurado ? (
            <>
              Configurado para o projeto <code>{config.project_id}</code> (
              {config.client_email}).
            </>
          ) : (
            'Nenhuma credencial configurada ainda — os alertas não vão gerar push.'
          )}
        </div>
      )}

      <form className="form-grid" onSubmit={salvar}>
        <label className="campo-largo">
          JSON da conta de serviço do Firebase
          <textarea
            rows={8}
            value={jsonColado}
            onChange={(e) => setJsonColado(e.target.value)}
            placeholder='{ "type": "service_account", "project_id": "...", ... }'
          />
          <small>
            Firebase Console → ícone de engrenagem → Configurações do projeto
            → aba <strong>Contas de serviço</strong> → Gerar nova chave
            privada. Cole aqui o conteúdo integral do arquivo baixado — ele
            não é salvo em nenhum outro lugar além desta configuração.
          </small>
        </label>

        <div className="form-acoes">
          <button type="submit" disabled={salvando || !jsonColado}>
            Salvar credencial
          </button>
          {config?.configurado && (
            <button
              type="button"
              className="secundario"
              onClick={remover}
              disabled={salvando}
            >
              Remover credencial
            </button>
          )}
          <button
            type="button"
            className="secundario"
            onClick={testar}
            disabled={testando || !config?.configurado}
          >
            Enviar push de teste (para mim)
          </button>
        </div>
      </form>

      {erro && <div className="alerta-erro">{erro}</div>}
      {aviso && <div className="alerta-sucesso">{aviso}</div>}

      <p className="muted-left">
        O teste só funciona depois de você ter entrado no aplicativo Android
        com o seu usuário pelo menos uma vez — é o login que registra o
        token do aparelho.
      </p>
    </div>
  );
}
