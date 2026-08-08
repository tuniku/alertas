# Alertas — aplicativo Android

App em Flutter que mostra no celular os alertas ativos do sistema, marcados como **"Disponível no aplicativo"** no cadastro do alerta.

Faz quatro coisas, e só: entrar, ver os alertas ativos, ver o histórico e encerrar um alerta. O cadastro de projetos, alertas, canais e usuários continua sendo pelo painel web.

## Ambiente

Requer **FVM** (que gerencia a versão do Flutter) e o **SDK do Android**. A versão do Flutter usada está fixada no `.fvmrc` — o FVM baixa exatamente ela, independentemente do que estiver instalado na máquina.

Todo comando roda **dentro desta pasta** e com o prefixo `fvm`:

```powershell
cd C:\Projetos\alertas\app
fvm flutter pub get
fvm flutter devices          # confirma que o aparelho aparece
fvm flutter run
```

**Depois** que o app subir (quando aparecerem os comandos `r`/`R`/`q` no terminal), abra outro PowerShell e crie o túnel USB:

```powershell
C:\dev\android-sdk\platform-tools\adb.exe reverse tcp:8000 tcp:8000
C:\dev\android-sdk\platform-tools\adb.exe reverse --list
```

A ordem importa: o `flutter run` reinicia o servidor do `adb` ao se conectar ao aparelho, e isso **apaga** os redirecionamentos criados antes. Feito nessa ordem, o túnel sobrevive.

Depois de criar o túnel, aperte `R` no terminal do Flutter para reiniciar o app. Se em algum momento aparecer "Sem conexão com o servidor", confira o túnel com `adb reverse --list` — vazio significa que ele caiu.

Rodar `flutter` sem o `fvm` (ou fora desta pasta) não funciona: não há Flutter global instalado, por opção.

## Para onde o app aponta

O endereço é **configurável dentro do app**: na tela de login, o rodapé mostra a URL em uso e abre um diálogo com os atalhos *Produção* e *Local (USB)*, além de um campo livre. A escolha fica salva no aparelho.

Isso existe para que **um único APK** sirva aos dois ambientes — a alternativa seria gerar um APK por ambiente, com o endereço fixado em tempo de compilação.

Trocar de servidor **encerra a sessão**: o token do Sanctum vale apenas no servidor que o emitiu, e mantê-lo produziria 401 em toda tela sem causa aparente.

O valor inicial (antes de qualquer escolha) segue esta ordem, em `lib/config.dart`:

| Situação | URL |
|---|---|
| `--dart-define=API_URL=...` | o que for informado |
| Release (APK no celular) | `https://alertas.tuniku.com/api` |
| Debug (rodando pelo cabo) | `http://localhost:8000/api` |

O `localhost` é o do **próprio celular** — ele só alcança o computador por causa do `adb reverse tcp:8000 tcp:8000`, que faz um túnel pelo cabo USB. Isso vale inclusive para o APK de release apontado para "Local (USB)": basta a depuração USB estar ligada e o túnel criado.

Se um dia voltar a usar o emulador, informe `http://10.0.2.2:8000/api` no campo livre: dentro do emulador, `localhost` é o próprio emulador, e `10.0.2.2` é o computador que o hospeda.

Para testar o app do emulador contra a produção:

```powershell
fvm flutter run --dart-define=API_URL=https://alertas.tuniku.com/api
```

## Gerar o APK

```powershell
fvm flutter build apk --release
```

O arquivo sai em `build\app\outputs\flutter-apk\app-release.apk`. Para instalar sem loja: jogue no Google Drive, abra pelo celular e autorize "instalar apps de fontes desconhecidas" quando o Android pedir.

A cada APK novo que for instalar por cima, incremente o número depois do `+` em `version:` no `pubspec.yaml` (`1.0.0+1` → `1.0.0+2`). O Android recusa instalar uma versão com número interno menor ou igual ao já instalado.

## Push (Firebase Cloud Messaging)

Todo alerta marcado como **"Disponível no aplicativo"** dispara um push, automaticamente, para todos os aparelhos com algum usuário logado — não é um canal selecionável por alerta, como Discord/Telegram/Tuya (esses continuam configuráveis por `tipo_disparo`; o push é ligado direto ao `disponivel_app`).

Duas credenciais do Firebase entram em jogo, e não são a mesma coisa:

| Arquivo | Papel | Onde fica |
|---|---|---|
| `android/app/google-services.json` | Identifica o app para **receber** push | Commitado no repositório — é seguro, é uma chave de API restrita por pacote, pensada para ir dentro do APK |
| JSON da conta de serviço | Autoriza o servidor a **enviar** push | Colado na tela **Configuração de push** do painel web, guardado na tabela `configuracoes` — nunca no git |

Depois de trocar `google-services.json` ou o `applicationId`, rode `fvm flutter pub get` antes do próximo `flutter run`/`build apk`.

Comportamento em cada estado do app:

- **Fechado ou em segundo plano**: o Android mostra a notificação sozinho na barra (usa o payload `notification` da mensagem, com o canal padrão do FCM — não precisou de configuração extra).
- **Aberto**: o Android não mostra nada (comportamento padrão dele); `lib/telas/inicio.dart` escuta `FirebaseMessaging.onMessage`, recarrega as duas abas e mostra um `SnackBar`.

O token do FCM é enviado ao backend (`POST /api/app/dispositivo`) sempre que a tela de alertas abre — tanto logo após o login quanto ao reabrir o app com uma sessão salva — e removido (`DELETE /api/app/dispositivo`) ao sair. O endpoint de registro faz *upsert* pelo token: se o mesmo celular logar com outro usuário, a posse do token passa para quem logou por último.

## Estrutura

| Arquivo | Papel |
|---|---|
| `lib/main.dart` | Entrada; decide entre login e lista conforme haja token salvo |
| `lib/config.dart` | Resolução da URL da API |
| `lib/api.dart` | Chamadas HTTP, token Sanctum e tradução de erros |
| `lib/modelos.dart` | `AlertaAtivo`, `AlertaLog` e `ErroApi` |
| `lib/telas/login.dart` | Tela de login |
| `lib/telas/inicio.dart` | Abas "Ativos" e "Histórico", e o encerramento |

Sem gerenciador de estado (Provider, Riverpod, Bloc): são duas telas e cinco chamadas de API. `setState` dá conta, e qualquer camada a mais custaria mais para entender do que economiza.

## Endpoints consumidos

Todos exigem `Authorization: Bearer <token>`, obtido em `POST /api/login`.

| Rota | Uso |
|---|---|
| `GET /api/app/alertas-ativos` | Aba "Ativos" |
| `GET /api/app/logs` | Aba "Histórico" |
| `POST /api/app/alertas-ativos/{id}/fechar` | Botão de encerrar |
| `POST /api/app/dispositivo` | Registra o token do FCM (ao abrir a tela de alertas) |
| `DELETE /api/app/dispositivo` | Remove o token do FCM (ao sair) |
| `POST /api/logout` | Sair |

Esses endpoints já filtram por `disponivel_app` no servidor — o app não envia nenhum parâmetro de filtro, e não teria como burlar isso.

O fechamento é **global**: encerra o alerta para todos os usuários, no app e no painel, e dispara o encerramento nos canais que têm um (hoje, apagar a lâmpada da Tuya).

## Permissões e rede

O `AndroidManifest.xml` principal declara `INTERNET` explicitamente. O template do Flutter só a inclui nos manifestos de debug/profile — sem essa linha, o APK de release abre normalmente mas nenhuma chamada funciona, com erro genérico no aparelho.

O `res/xml/network_security_config.xml` libera HTTP sem TLS **apenas** para `10.0.2.2`, `10.0.3.2` e loopback, para o desenvolvimento contra o Laravel local. O APK continua incapaz de trafegar em texto claro para qualquer servidor da internet.
