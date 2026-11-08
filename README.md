# Pedidos de Viagem Corporativa

Microsserviço em **Laravel 13** que gerencia pedidos de viagem corporativa por uma API REST. Ele permite:

- criar, consultar e listar pedidos, com filtros por status, destino e período;
- aprovar ou cancelar pedidos, o que só administradores podem fazer;
- notificar o solicitante quando o pedido é aprovado ou cancelado.

| | |
|---|---|
| Linguagem / framework | PHP 8.4 · Laravel 13 |
| Banco de dados | MySQL 8.4 (com migrations) |
| Autenticação | Laravel Sanctum (token Bearer) |
| Fila / notificações | Fila `database` com worker próprio · Mailpit para ver os e-mails |
| Tarefas agendadas | Scheduler do Laravel em container próprio |
| Testes | PHPUnit (77 testes) · coleção Postman/Newman (78 verificações) |
| Front-end (extra) | Vue 3 + Vite |
| Ambiente | Docker Compose |

**Sumário:**
[1. Caminhos e decisões](#1-caminhos-e-decisões) ·
[2. Instalar as dependências](#2-instalar-as-dependências) ·
[3. Executar com Docker](#3-executar-o-serviço-localmente-com-docker) ·
[4. Configurar o ambiente](#4-configurar-o-ambiente) ·
[5. Executar os testes](#5-executar-os-testes) ·
[6. Informações adicionais](#6-informações-adicionais)

---

## 1. Caminhos e decisões

**Como o teste foi conduzido**

1. **Ambiente primeiro.** O projeto inteiro roda em Docker, com Nginx, PHP-FPM, MySQL, worker de fila, agendador e Mailpit. Quem avalia não precisa ter PHP, Composer ou Node instalados.
2. **Domínio no centro.** A regra mais importante do enunciado é "só cancela se ainda não foi aprovado". Ela foi modelada como uma pequena máquina de estados no enum `StatusPedido`, e o model `PedidoViagem` é o único caminho para mudar o status. Assim, a regra vale para qualquer porta de entrada (API, comando, job) e é testada sem HTTP.
3. **Cada responsabilidade no seu lugar:**
   - **FormRequest** valida a entrada;
   - **Policy** decide quem pode, com resposta 403;
   - **Service** orquestra a transação, a trava e a notificação;
   - **API Resource** define o JSON de saída;
   - os **controllers** ficam enxutos.
4. **Testes acompanhando cada requisito**, rodando no MySQL (o mesmo banco de produção), além de uma coleção do Postman que exercita a API de verdade.
5. **Em português**: tabelas, colunas, classes, rotas, JSON, mensagens e comentários fazem sentido para quem fala português. A exceção são as convenções do framework, como `index`/`store` e as tabelas internas do Laravel.

**Principais decisões**

| Decisão | Motivo |
|---|---|
| Só administrador altera status, e **nunca o próprio pedido** | O enunciado diz que o solicitante não pode alterar o status. Bloquear também o admin no próprio pedido evita autoaprovação. |
| `SELECT … FOR UPDATE` ao mudar o status | Se dois administradores aprovarem e cancelarem ao mesmo tempo, o segundo vê o status já alterado e é recusado. |
| Notificação enviada **após o commit** e **pela fila** | Ninguém é avisado de algo que foi desfeito, e um SMTP lento não deixa a API lenta. |
| **UUID v7** como ID do pedido | Não expõe quantos pedidos existem e não permite "chutar" IDs. |
| Regra de negócio violada responde **422**; falta de permissão, **403** | A autorização roda antes da validação, e cada erro tem uma mensagem clara em português. |
| Nome do solicitante vem do **usuário logado** | `status`, `usuario_id` e `nome_solicitante` enviados no corpo são ignorados, então ninguém forja um pedido. |
| API versionada (`/api/v1`) e com limite de requisições | Permite evoluir o contrato sem quebrar clientes e protege o login contra força bruta. |

A tabela completa de decisões está na seção [6.4](#64-arquitetura-e-decisões-detalhadas).

**Além do que foi pedido**

- **Auditoria:** registra quem aprovou ou cancelou cada pedido, e quando.
- **Gestão da equipe:** o administrador cadastra colaboradores e outros administradores; não há cadastro público. Com mais de um administrador, um aprova as viagens do outro.
- **Pedidos atrasados:** pedidos parados há mais de 48 h aparecem destacados, com o tempo de espera, e geram um lembrete automático aos administradores.
- **Front-end em Vue** e **coleção do Postman** pronta para importar.

---

## 2. Instalar as dependências

O único pré-requisito na máquina é o **[Docker Desktop](https://docs.docker.com/get-docker/)** (ou Docker Engine com o plugin Compose).

```bash
git clone https://github.com/mauricio159/TesteBackEndOnfly.git
cd TesteBackEndOnfly
```

As dependências são instaladas **automaticamente** dentro dos containers na primeira subida:

| Dependências | Quem instala | Onde ficam |
|---|---|---|
| PHP (Composer) | O container `app` roda `composer install` se o `vendor` estiver vazio | Volume Docker `vendor` |
| Front-end (npm) | O container `frontend` roda `npm install` antes de subir o Vite | Volume Docker `frontend-node-modules` |
| Extensões do PHP | O `Dockerfile` (`pdo_mysql`, `intl`, `bcmath`, `zip`, `pcntl`, `opcache`) | Imagem `pedidos-viagem-php` |

Se quiser reinstalar manualmente, por exemplo depois de alterar o `composer.json` ou o `package.json`:

```bash
docker compose exec app composer install
docker compose exec frontend npm install
```

---

## 3. Executar o serviço localmente (com Docker)

```bash
docker compose up -d --build                  # sobe tudo (a primeira vez leva alguns minutos)
docker compose exec app php artisan db:seed   # cria usuários e pedidos de demonstração
```

Na primeira subida, o container `app` faz sozinho:

1. instala as dependências;
2. cria o `.env` a partir do `.env.example` e gera a `APP_KEY`;
3. roda as **migrations**.

Os outros containers só sobem depois que ele fica saudável.

| Serviço | Endereço |
|---|---|
| **API** | http://localhost:8000/api/v1 |
| Front-end (extra) | http://localhost:5173 |
| E-mails enviados (Mailpit) | http://localhost:8025 |
| Health check | http://localhost:8000/up |
| MySQL | `localhost:3307` (veja a [seção 4](#4-configurar-o-ambiente)) |

| Container | Papel |
|---|---|
| `nginx` | Recebe o HTTP na porta 8000 e repassa ao PHP-FPM |
| `app` | Aplicação Laravel (PHP-FPM) |
| `queue` | Worker da fila: envia as notificações |
| `scheduler` | Roda as tarefas agendadas (lembrete de hora em hora) |
| `mysql` | Banco de dados |
| `mailpit` | Servidor de e-mail de mentira, para ver os e-mails enviados |
| `frontend` | Front-end Vue (opcional) |

**Usuários de demonstração.** Todos usam a senha `senha123`.

| E-mail | Quem é |
|---|---|
| `admin@viagens.test` | Carla Mendes, **administradora** |
| `diretor@viagens.test` | Diego Ramos, **administrador** (pode aprovar as viagens da Carla) |
| `ana@viagens.test` | Ana Souza, colaboradora |
| `bruno@viagens.test` | Bruno Lima, colaborador |

O que já vem cadastrado:

- cada colaborador tem pedidos em todos os status, e um deles está parado há dias, aparecendo como **atrasado**;
- a Carla tem uma viagem para Brasília aguardando decisão, que só o Diego pode aprovar.

**Primeira chamada à API:**

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ana@viagens.test","senha":"senha123"}'
```

A lista completa de endpoints está na [seção 6.1](#61-api).

---

## 4. Configurar o ambiente

### Variáveis de ambiente

Na primeira subida, o `.env` é criado a partir do `.env.example`, que já vem pronto para o Docker. Nada precisa ser alterado para rodar. As variáveis mais relevantes são:

| Variável | Valor padrão | Para quê |
|---|---|---|
| `APP_KEY` | gerada sozinha | Chave de criptografia do Laravel |
| `APP_TIMEZONE` | `America/Sao_Paulo` | "Hoje" na validação da data de ida segue o horário de Brasília |
| `APP_LOCALE` | `pt_BR` | Mensagens de validação e e-mails em português |
| `DB_HOST` / `DB_PORT` | `mysql` / `3306` | Nome do serviço do banco na rede do Docker (porta interna) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `viagens_corporativas` / `viagens` / `segredo` | Credenciais do banco (as mesmas do `compose.yaml`) |
| `QUEUE_CONNECTION` | `database` | As notificações vão para a fila e o container `queue` as processa |
| `MAIL_HOST` / `MAIL_PORT` | `mailpit` / `1025` | Os e-mails vão para o Mailpit (http://localhost:8025) |
| `PEDIDO_ALERTA_APOS_HORAS` | `48` | Depois de quantas horas um pedido "solicitado" fica **atrasado** |
| `PEDIDO_REPETIR_LEMBRETE_APOS_HORAS` | `24` | Intervalo mínimo entre dois lembretes do mesmo pedido |

Depois de alterar o `.env`:

```bash
docker compose exec app php artisan config:clear
docker compose restart queue scheduler
```

### Banco de dados

O MySQL roda no container `mysql`, e os dados ficam no volume `mysql-dados`, então sobrevivem a reinícios.

| | |
|---|---|
| Banco da aplicação | `viagens_corporativas`, criado pelo próprio container |
| Banco dos testes | `viagens_corporativas_teste`, criado pelo script `docker/mysql/init/01-criar-banco-de-testes.sql` |
| Acesso externo (DBeaver, Workbench…) | host `localhost`, porta **`3307`**, usuário `viagens`, senha `segredo` |
| Acesso pelo terminal | `docker compose exec mysql mysql -uviagens -psegredo viagens_corporativas` |
| Migrations | Rodam sozinhas na subida; manualmente: `docker compose exec app php artisan migrate` |
| Zerar e popular de novo | `docker compose exec app php artisan migrate:fresh --seed` |

A porta do host é **3307**, e não 3306, para não conflitar com um MySQL que já esteja instalado na máquina.

| Tabela | Conteúdo |
|---|---|
| `usuarios` | Colaboradores e administradores (`nome`, `email`, `senha`, `administrador`) |
| `pedidos_viagem` | Os pedidos (`usuario_id`, `nome_solicitante`, `destino`, `data_ida`, `data_volta`, `status`), a decisão (`decidido_por_id`, `decidido_em`) e o controle de lembrete (`ultimo_lembrete_em`) |
| `notifications` | Avisos de aprovação/cancelamento e lembretes |
| `jobs`, `failed_jobs`, `cache`, `personal_access_tokens`, `migrations` | Tabelas internas do Laravel e do Sanctum |

### Portas

Se alguma porta já estiver em uso na sua máquina, altere o lado esquerdo do mapeamento em `compose.yaml` (por exemplo, `"8080:80"` no `nginx`).

| Porta | Serviço |
|---|---|
| 8000 | API (nginx) |
| 5173 | Front-end |
| 8025 | Mailpit |
| 3307 | MySQL |

---

## 5. Executar os testes

```bash
docker compose exec app php artisan test
```

Para rodar só um arquivo ou só um teste:

```bash
docker compose exec app php artisan test --filter=AlterarStatusPedidoTest
docker compose exec app php artisan test --filter=test_pedido_aprovado_nao_pode_ser_cancelado
```

- Os testes usam o banco **separado** `viagens_corporativas_teste`. Rodar os testes nunca apaga os dados da demonstração.
- Os testes rodam no **MySQL**, e não em SQLite, para ter o mesmo comportamento de produção em `LIKE`, datas e travas.

**O que é coberto (77 testes / 250 verificações):**

- **Unit:** as 9 combinações de mudança de status (*data provider*).
- **Autenticação:** login, senha errada, perfil, logout e cadastro público inexistente.
- **Criação:** validações, datas (passado, volta antes da ida, formato) e tentativa de forjar solicitante ou status.
- **Consulta e listagem:**
  - permissões (dono, administrador, outra pessoa) e 404;
  - cada filtro e filtros combinados;
  - paginação e filtros inválidos.
- **Mudança de status:**
  - aprovar e cancelar;
  - **aprovado → cancelado bloqueado**;
  - solicitante não altera o próprio pedido, admin não aprova a própria viagem;
  - 403 antes de 422.
- **Notificação:** destinatário, canais, conteúdo em português, fila, nenhum envio quando a operação falha e o teste ponta a ponta até `GET /notificacoes`.
- **Extras:** auditoria da decisão, equipe, pedidos atrasados e lembretes.

**Coleção do Postman.** Ela fica em `postman/`, com 36 requisições e 78 verificações. Importe `postman/pedidos-viagem.postman_collection.json` no Postman e clique em **Run collection**, ou rode pelo terminal sem instalar nada:

```bash
docker run --rm --network pedidos-viagem_default -v "${PWD}/postman:/etc/newman" postman/newman:6-alpine run pedidos-viagem.postman_collection.json --env-var "base_url=http://nginx/api/v1"
```

O guia completo está em [postman/README.md](postman/README.md).

Padronização do código (Laravel Pint):

```bash
docker compose exec app ./vendor/bin/pint --test
```

---

## 6. Informações adicionais

### 6.1 API

Base: `http://localhost:8000/api/v1`. Nas rotas protegidas, envie `Authorization: Bearer <token>`. Todas as respostas são JSON.

| Método | Rota | Quem pode |
|---|---|---|
| POST | `/auth/login` | Qualquer pessoa (`email`, `senha`); devolve o token |
| POST | `/auth/logout` | Usuário logado; revoga o token atual |
| GET | `/auth/perfil` | Usuário logado |
| POST | `/pedidos-viagem` | Usuário logado (`destino`, `data_ida`, `data_volta`) |
| GET | `/pedidos-viagem` | Colaborador vê os seus; administrador vê todos |
| GET | `/pedidos-viagem/{id}` | Dono do pedido ou administrador |
| PATCH | `/pedidos-viagem/{id}/status` | Só administrador, e nunca no próprio pedido (`status`: `aprovado` ou `cancelado`) |
| GET | `/notificacoes` | Usuário logado (decisões e lembretes) |
| GET | `/usuarios` | Só administrador: lista a equipe (`?perfil=colaborador` ou `administrador`) |
| POST | `/usuarios` | Só administrador: cadastra colaborador ou, com `"administrador": true`, outro administrador |

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

**Filtros da listagem.** Todos são opcionais e combináveis; as datas vão em `AAAA-MM-DD`.

| Parâmetro | Efeito |
|---|---|
| `status` | `solicitado`, `aprovado` ou `cancelado` |
| `destino` | Busca parcial, sem diferenciar maiúsculas (`?destino=lisboa`) |
| `criado_de` / `criado_ate` | Pedidos **feitos** dentro do período |
| `viagem_de` / `viagem_ate` | Viagens que acontecem **inteiras** dentro do período |
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

Exemplo de 422 por regra de negócio:

```json
{
  "message": "Este pedido já foi aprovado e, por isso, não pode mais ser cancelado.",
  "status_atual": "aprovado",
  "status_solicitado": "cancelado"
}
```

### 6.2 Regras de negócio

```
solicitado ──► aprovado    (fim da linha)
     │
     └──────► cancelado   (fim da linha)
```

- Todo pedido nasce como `solicitado`.
- Só **administradores** mudam o status, e nunca o do **próprio** pedido.
- Pedido **aprovado não pode ser cancelado**, e cancelado não pode ser aprovado.
- A data de ida não pode estar no passado, e a volta não pode ser antes da ida.
- Ao aprovar ou cancelar, o solicitante recebe **e-mail** e uma **notificação** no sistema, ambos dizendo quem decidiu.
- Toda decisão grava **quem** e **quando**. Uma tentativa recusada não altera esse registro.
- Não existe cadastro público: o **administrador** cadastra a equipe.
- Pedido solicitado há mais de 48 h fica **atrasado**:
  - aparece destacado e entra no filtro `atrasados=1`;
  - gera um **lembrete** de hora em hora para os administradores, exceto o próprio solicitante, sem repetir antes de 24 h.

### 6.3 Organização do código

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
docker/               # Dockerfile, nginx, script do banco de testes
frontend/             # Vue 3 (extra)
postman/              # coleção pronta para importar
```

### 6.4 Arquitetura e decisões detalhadas

| Decisão | Motivo |
|---|---|
| **Regra de status no Enum e no Model** | Vale para qualquer porta de entrada e é testável sem HTTP. |
| **Service** para os casos de uso | Concentra transação, trava e notificação; o controller só traduz HTTP. |
| **Policy** para autorização | Separa "quem pode" de "o que é válido". |
| **Exceção de domínio** convertida em 422 no `bootstrap/app.php` | O domínio não conhece HTTP. A exceção não vai para o log de erros, porque é um "não" esperado. |
| **`status` como texto + Enum do PHP** (em vez de `ENUM` do MySQL) | Criar um status novo não exige `ALTER TABLE`. |
| **API Resources** | Separam o JSON público da estrutura da tabela. |
| **Sanctum** | Pacote oficial do Laravel. Tokens guardados como hash e revogáveis no logout. |
| **Limite de requisições** | 60 por minuto por usuário na API; 10 por minuto por e-mail+IP no login. |
| **Fuso `America/Sao_Paulo`** | Em UTC, às 22h já é "amanhã", e a validação da data de ida erraria para o usuário brasileiro. |
| **Containers separados**, com health checks | Cada serviço reinicia e escala de forma independente, e a ordem de subida é garantida. |
| **Decisão em duas colunas**, sem tabela de histórico | Aprovado e cancelado são finais: cada pedido tem no máximo uma decisão. |
| **Migration nova** para a auditoria | É assim que se evolui um banco que já está em uso. |
| **"Atrasado" calculado na hora**, não gravado | Depende do relógio; uma coluna ficaria desatualizada. |
| **Lembrete por comando agendado**, só no canal database | Com vários pedidos parados, um e-mail por pedido viraria spam. |
| **Sem cadastro público** | Viagem corporativa envolve dinheiro da empresa: quem decide quem tem acesso é a empresa. |
| **Nomes em português**, exceto convenções do framework | Tabelas internas do Laravel e nomes como `index`/`store` mantêm o padrão, porque o framework depende deles. |

### 6.5 Front-end (extra)

Interface simples em **Vue 3**, em http://localhost:5173, para demonstrar o fluxo completo. Ela oferece:

- login com acesso rápido aos usuários de demonstração;
- lista com todos os filtros e os botões **Aprovar** e **Cancelar** para o administrador;
- pedidos **atrasados destacados**, com o tempo de espera;
- quem decidiu cada pedido;
- novo pedido com os erros de validação da API;
- notificações;
- tela **Equipe** para o administrador.

O Vite repassa `/api` para o Laravel, então não é preciso configurar CORS. Esconder botões é só conforto visual: quem garante as regras é a API.

### 6.6 Dicas e solução de problemas

| Situação | O que fazer |
|---|---|
| Alterei código PHP e a notificação não mudou | O worker guarda o código em memória: `docker compose exec app php artisan queue:restart` |
| Quero ver os lembretes sem esperar 1 hora | `docker compose exec app php artisan pedidos:lembrar-pendentes` |
| Recebi **429** rodando a coleção várias vezes | O login tem limite de 10 tentativas por minuto; aguarde um minuto |
| Porta ocupada ao subir | Troque a porta do host em `compose.yaml` (veja a [seção 4](#portas)) |
| Começar do zero | `docker compose down -v`, depois `docker compose up -d --build` e `docker compose exec app php artisan db:seed` |

Duas observações sobre o ambiente:

- **Desempenho no Windows:** o `vendor` fica num volume do Docker, e não na pasta do projeto. Ler os ~7 mil arquivos dele a partir da pasta do Windows deixava cada requisição com ~3 s; no volume, cai para ~40 ms.
- **Credenciais:** as do `compose.yaml` e do `.env.example` (`segredo`, `root`, `senha123`) são só para desenvolvimento local. Em produção viriam de variáveis de ambiente seguras ou de um cofre de segredos.

**Comandos úteis**

```bash
docker compose ps                                   # estado dos containers
docker compose logs -f app queue scheduler          # acompanhar logs
docker compose exec app php artisan schedule:list   # tarefas agendadas
docker compose exec app php artisan route:list --path=api   # rotas da API
docker compose down                                 # parar tudo (mantém o banco)
```
