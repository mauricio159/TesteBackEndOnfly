# Detalhes técnicos

Complemento do [README](../README.md) para quem quer se aprofundar: a API completa, as decisões técnicas, a organização do código e o que os testes cobrem.

- [API](#api)
- [Regras de negócio](#regras-de-negócio)
- [Decisões técnicas](#decisões-técnicas)
- [Organização do código](#organização-do-código)
- [Banco de dados](#banco-de-dados)
- [Ambiente Docker](#ambiente-docker)
- [Testes](#testes)
- [Front-end](#front-end)
- [Comandos úteis](#comandos-úteis)

---

## API

Base: `http://localhost:8000/api/v1`. Nas rotas protegidas, envie `Authorization: Bearer <token>`. Todas as respostas são JSON, com campos e mensagens em português.

| Método | Rota | Quem pode |
|---|---|---|
| POST | `/auth/login` | Qualquer pessoa (`email`, `senha`); devolve o token |
| POST | `/auth/logout` | Usuário logado; revoga o token atual |
| GET | `/auth/perfil` | Usuário logado |
| POST | `/pedidos-viagem` | Usuário logado (`destino`, `data_ida`, `data_volta`) |
| GET | `/pedidos-viagem` | Colaborador vê os seus; administrador vê todos |
| GET | `/pedidos-viagem/{id}` | Dono do pedido ou administrador |
| PATCH | `/pedidos-viagem/{id}/status` | Só administrador, nunca no próprio pedido (`status`: `aprovado` ou `cancelado`) |
| GET | `/notificacoes` | Usuário logado (decisões e lembretes) |
| GET | `/usuarios` | Só administrador; filtro opcional `?perfil=colaborador` ou `administrador` |
| POST | `/usuarios` | Só administrador (`nome`, `email`, `senha`, `senha_confirmacao`, `administrador` opcional) |

**Exemplo de pedido retornado:**

```json
{
  "data": {
    "id": "01a1b2ea-e9ce-721d-a8db-cda6efe9e993",
    "solicitante": { "id": 2, "nome": "Ana Souza" },
    "destino": "Lisboa, Portugal",
    "data_ida": "2026-12-10",
    "data_volta": "2026-12-15",
    "status": "aprovado",
    "status_rotulo": "Aprovado",
    "decisao": { "por": { "id": 1, "nome": "Carla Mendes" }, "em": "2026-11-07T17:39:00-03:00" },
    "tempo_aguardando": null,
    "atrasado": false,
    "criado_em": "2026-11-07T16:51:42-03:00",
    "atualizado_em": "2026-11-07T17:39:00-03:00"
  }
}
```

| Campo | Significado |
|---|---|
| `decisao` | Quem aprovou ou cancelou, e quando. `null` enquanto o pedido aguarda. |
| `tempo_aguardando` | Há quanto tempo espera decisão ("há 3 dias e 5 horas"). `null` se já foi decidido. |
| `atrasado` | `true` quando está "solicitado" há mais de `PEDIDO_ALERTA_APOS_HORAS`. |

**Filtros da listagem.** Todos são opcionais e combináveis; as datas vão em `AAAA-MM-DD`.

| Parâmetro | Efeito |
|---|---|
| `status` | `solicitado`, `aprovado` ou `cancelado` |
| `destino` | Busca parcial, sem diferenciar maiúsculas (`?destino=lisboa`) |
| `criado_de` / `criado_ate` | Pedidos **feitos** dentro do período |
| `viagem_de` / `viagem_ate` | Viagens que acontecem **inteiras** dentro do período (ida ≥ início e volta ≤ fim) |
| `atrasados=1` | Só pedidos parados além do prazo |
| `por_pagina` / `pagina` | Paginação (padrão 15, máximo 100) |

**Códigos de resposta:**

| Código | Quando |
|---|---|
| 200 / 201 / 204 | Sucesso |
| 401 | Sem token, token inválido ou e-mail/senha errados |
| 403 | Sem permissão (ex.: solicitante tentando aprovar o próprio pedido) |
| 404 | Pedido não encontrado |
| 422 | Dado inválido **ou** regra de negócio violada |
| 429 | Muitas requisições em pouco tempo |

Exemplo de regra de negócio violada:

```json
{
  "message": "Este pedido já foi aprovado e, por isso, não pode mais ser cancelado.",
  "status_atual": "aprovado",
  "status_solicitado": "cancelado"
}
```

---

## Regras de negócio

```
solicitado ──► aprovado    (fim da linha)
     │
     └──────► cancelado   (fim da linha)
```

- Todo pedido nasce como `solicitado`.
- Só administradores mudam o status, e nunca o do próprio pedido.
- Pedido aprovado não pode ser cancelado, e cancelado não pode ser aprovado.
- O nome do solicitante vem do usuário logado. Se o JSON trouxer `status`, `usuario_id` ou `nome_solicitante`, esses campos são ignorados.
- A data de ida não pode estar no passado, e a volta não pode ser antes da ida. "Hoje" segue o horário de Brasília.
- Ao aprovar ou cancelar, o solicitante recebe **e-mail** e uma **notificação** no sistema, ambos dizendo quem decidiu.
- Toda decisão grava **quem** e **quando** (`decidido_por_id`, `decidido_em`). Uma tentativa recusada não altera esse registro.
- Não existe cadastro público: o administrador cadastra colaboradores e outros administradores.
- Pedido solicitado há mais de `PEDIDO_ALERTA_APOS_HORAS` (48 h) fica **atrasado**:
  - aparece destacado e entra no filtro `atrasados=1`;
  - gera um lembrete de hora em hora para os administradores, exceto o próprio solicitante;
  - o lembrete do mesmo pedido não se repete antes de `PEDIDO_REPETIR_LEMBRETE_APOS_HORAS` (24 h).

---

## Decisões técnicas

| Decisão | Motivo |
|---|---|
| **Regra de status no Enum e no Model**, não no controller | Vale para qualquer porta de entrada (API, comando, job) e é testável sem HTTP. |
| **Service** para os casos de uso | Concentra transação, trava e notificação; o controller só traduz HTTP. |
| **Policy** para autorização, rodando antes da validação | Separa "quem pode" de "o que é válido". Quem não tem permissão recebe 403 sem nem passar pela validação. |
| **Exceção de domínio** convertida em 422 no `bootstrap/app.php` | O domínio não conhece HTTP. A exceção não vai para o log de erros, porque é um "não" esperado, e não um bug. |
| **`SELECT … FOR UPDATE`** ao mudar o status | Se dois administradores decidirem ao mesmo tempo, o segundo vê o status já alterado e é recusado. |
| **Notificação após o commit e enfileirada** | Ninguém é avisado de algo que foi desfeito, e um SMTP lento não deixa a API lenta (até 3 tentativas). |
| **UUID v7** como ID do pedido | Não expõe volume nem permite "chutar" IDs, e ainda é ordenável no tempo (bom para índice). |
| **`status` como texto + Enum do PHP** (em vez de `ENUM` do MySQL) | Criar um status novo não exige `ALTER TABLE`. |
| **API Resources** | Separam o JSON público da estrutura da tabela. |
| **Versionamento `/api/v1`** | Permite evoluir o contrato sem quebrar quem já consome. |
| **Sanctum** | Pacote oficial do Laravel. Tokens guardados como hash e revogáveis no logout. |
| **Limite de requisições** | 60 por minuto por usuário na API; 10 por minuto por e-mail+IP no login, contra força bruta. |
| **Testes no MySQL**, não no SQLite | Mesmo comportamento de produção em `LIKE`, datas e travas. |
| **Fuso `America/Sao_Paulo`** | Em UTC, às 22h já é "amanhã", e a validação da data de ida erraria para o usuário brasileiro. |
| **Decisão em duas colunas**, sem tabela de histórico | Aprovado e cancelado são finais: cada pedido tem no máximo uma decisão. |
| **Migration nova** para a auditoria | É assim que se evolui um banco que já está em uso. |
| **"Atrasado" calculado na hora**, não gravado | Depende do relógio; uma coluna ficaria desatualizada. |
| **Lembrete por comando agendado**, só no canal database | É o jeito do Laravel para tarefas periódicas. Com vários pedidos parados, um e-mail por pedido viraria spam. |
| **Sem cadastro público** | Viagem corporativa envolve dinheiro da empresa: quem decide quem tem acesso é a empresa. Também evita que alguém se autopromova a administrador. |
| **Nomes em português**, exceto convenções do framework | As tabelas internas do Laravel e nomes como `index`/`store` mantêm o padrão, porque o framework depende deles. |
| **`vendor` num volume do Docker** | No Windows, ler os ~7 mil arquivos do `vendor` da pasta montada deixava cada requisição com ~3 s; no volume, cai para ~40 ms. |

---

## Organização do código

```
app/
├── Console/Commands/LembrarPedidosPendentes.php  # lembrete de pedidos parados (agendado)
├── Enums/StatusPedido.php                 # os 3 status e quais mudanças são permitidas
├── Exceptions/MudancaDeStatusInvalida.php # regra de status violada → 422
├── Http/
│   ├── Controllers/Api/V1/                # controllers enxutos
│   ├── Middleware/ForcarRespostaJson.php
│   ├── Requests/                          # validação e autorização da entrada
│   └── Resources/                         # formato do JSON de saída
├── Models/                                # PedidoViagem (regras, filtros, atraso), Usuario
├── Notifications/                         # StatusDoPedidoAlterado, LembretePedidoPendente
├── Policies/                              # PedidoViagemPolicy, UsuarioPolicy
└── Services/PedidoViagemService.php       # casos de uso (transação, trava, notificação)

bootstrap/app.php     # rotas, middlewares e tratamento de erros da API
config/pedidos.php    # prazos de atraso e de lembrete
routes/api.php        # rotas /api/v1
routes/console.php    # agenda do lembrete
database/             # migrations, factories e seeder
lang/pt_BR/           # mensagens de validação em português
tests/                # Unit e Feature
docker/               # Dockerfile, nginx, php.ini, script do banco de testes
frontend/             # Vue 3
postman/              # coleção pronta para importar
```

---

## Banco de dados

| Tabela | Conteúdo |
|---|---|
| `usuarios` | Colaboradores e administradores (`nome`, `email`, `senha`, `administrador`) |
| `pedidos_viagem` | Os pedidos (`usuario_id`, `nome_solicitante`, `destino`, `data_ida`, `data_volta`, `status`), a decisão (`decidido_por_id`, `decidido_em`) e o controle de lembrete (`ultimo_lembrete_em`) |
| `notifications` | Avisos de aprovação/cancelamento e lembretes |
| `jobs`, `failed_jobs`, `cache`, `personal_access_tokens`, `migrations` | Tabelas internas do Laravel e do Sanctum |

Para acessar pelo terminal:

```bash
docker compose exec mysql mysql -uviagens -psegredo viagens_corporativas
```

---

## Ambiente Docker

| Container | Papel |
|---|---|
| `nginx` | Recebe o HTTP na porta 8000 e repassa ao PHP-FPM |
| `app` | Aplicação Laravel (PHP-FPM). Na subida, instala dependências, cria o `.env` e roda as migrations |
| `queue` | Worker da fila: envia as notificações |
| `scheduler` | Roda as tarefas agendadas (lembrete de hora em hora) |
| `mysql` | Banco de dados (porta 3307 no host) |
| `mailpit` | Servidor de e-mail de mentira (http://localhost:8025) |
| `frontend` | Front-end Vue (http://localhost:5173) |

Os health checks garantem a ordem de subida: o `app` espera o MySQL, e os demais esperam o `app`.

**Todas as variáveis do `.env.example`:**

| Variável | Padrão | Para quê |
|---|---|---|
| `APP_KEY` | gerada sozinha | Chave de criptografia do Laravel |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso da aplicação |
| `APP_LOCALE` | `pt_BR` | Mensagens em português |
| `DB_HOST` / `DB_PORT` | `mysql` / `3306` | Endereço do banco dentro da rede do Docker |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `viagens_corporativas` / `viagens` / `segredo` | Credenciais (as mesmas do `compose.yaml`) |
| `QUEUE_CONNECTION` | `database` | Fila processada pelo container `queue` |
| `MAIL_HOST` / `MAIL_PORT` | `mailpit` / `1025` | Envio de e-mail para o Mailpit |
| `PEDIDO_ALERTA_APOS_HORAS` | `48` | Prazo para um pedido ficar atrasado |
| `PEDIDO_REPETIR_LEMBRETE_APOS_HORAS` | `24` | Intervalo entre lembretes do mesmo pedido |

---

## Testes

**77 testes / 250 verificações (PHPUnit), rodando no MySQL:**

- **Unit:** as 9 combinações de mudança de status (*data provider*).
- **Autenticação:** login, senha errada, perfil, logout e cadastro público inexistente.
- **Criação:** validações, datas (passado, volta antes da ida, formato) e tentativa de forjar solicitante ou status.
- **Consulta e listagem:**
  - permissões (dono, administrador, outra pessoa) e 404;
  - cada filtro e filtros combinados;
  - paginação e filtros inválidos.
- **Mudança de status:**
  - aprovar e cancelar;
  - aprovado → cancelado bloqueado;
  - solicitante não altera o próprio pedido, admin não aprova a própria viagem;
  - 403 antes de 422.
- **Notificação:** destinatário, canais, conteúdo em português, fila, nenhum envio quando a operação falha e o teste ponta a ponta até `GET /notificacoes`.
- **Auditoria:** quem e quando ficam gravados e não são sobrescritos por tentativa recusada.
- **Equipe:** cadastro de colaborador e administrador, permissão, validação, listagem e o novo admin aprovando a viagem de quem o cadastrou.
- **Atrasados e lembretes:** marcação, tempo de espera, limite configurável, filtro, quem recebe, sem repetição antes de 24 h e agendamento.

Para rodar só uma parte:

```bash
docker compose exec app php artisan test --filter=AlterarStatusPedidoTest
```

**Coleção Postman:** 36 requisições e 78 verificações. Também roda pelo terminal com o Newman:

```bash
docker run --rm --network pedidos-viagem_default -v "${PWD}/postman:/etc/newman" postman/newman:6-alpine run pedidos-viagem.postman_collection.json --env-var "base_url=http://nginx/api/v1"
```

**Padronização do código (Laravel Pint):**

```bash
docker compose exec app ./vendor/bin/pint --test
```

---

## Front-end

Interface simples em **Vue 3** (http://localhost:5173) para demonstrar o fluxo completo:

- login com acesso rápido aos usuários de demonstração;
- lista com todos os filtros e os botões **Aprovar** e **Cancelar** para o administrador, com confirmação na própria linha;
- pedidos atrasados destacados, com o tempo de espera atualizado sozinho e um aviso no topo;
- coluna "Decidido por" e, no detalhe, quem aprovou ou cancelou e quando;
- novo pedido, mostrando os erros de validação da API embaixo de cada campo;
- notificações (decisões e lembretes);
- tela **Equipe** para o administrador.

O Vite repassa `/api` para o Laravel, então não é preciso configurar CORS. Esconder botões é só conforto visual: quem garante as regras é a API.

Código em `frontend/src`:

- `api.js`: única porta de conversa com a API;
- `sessao.js`: token do usuário logado;
- `relogio.js`: tempo de espera;
- `paginas/` e `componentes/`.

---

## Comandos úteis

```bash
docker compose ps                                          # estado dos containers
docker compose logs -f app queue scheduler                 # acompanhar logs
docker compose exec app php artisan route:list --path=api  # rotas da API
docker compose exec app php artisan schedule:list          # tarefas agendadas
docker compose exec app php artisan pedidos:lembrar-pendentes   # gerar os lembretes agora
docker compose exec app php artisan migrate:fresh --seed   # zerar o banco e popular de novo
docker compose exec app php artisan queue:restart          # depois de mudar código PHP
docker compose down                                        # parar tudo (mantém o banco)
docker compose down -v                                     # parar e apagar banco e dependências
```
