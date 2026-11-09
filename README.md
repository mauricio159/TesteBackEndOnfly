# Pedidos de Viagem Corporativa

Microsserviço em **Laravel 13** para gerenciar viagens corporativas: o colaborador faz o pedido, o administrador aprova ou cancela, e quem pediu recebe um aviso por e-mail e no sistema.

Para rodar, você só precisa do **Docker**.

---

## 1. Os caminhos e decisões que tomei

**Comecei pelo ambiente.** Montei tudo em Docker para que qualquer pessoa rode o projeto com dois comandos, sem instalar PHP, Composer ou Node na máquina.

**Coloquei a regra mais importante no centro.** O enunciado diz que só dá para cancelar um pedido que ainda não foi aprovado. Essa regra mora em um único lugar: o enum `StatusPedido` e o model `PedidoViagem`. Assim ela vale para qualquer porta de entrada, seja a API, um comando ou um job, e é fácil de testar.

**Dei a cada parte do código uma função clara:**

- a validação fica no FormRequest;
- a permissão ("quem pode") fica na Policy;
- a regra de negócio fica no model;
- o Service coordena a operação (transação, trava e notificação);
- o Resource define o JSON de resposta.

Os controllers só ligam essas pontas.

**Pensei em segurança desde o início:**

- Só administrador aprova ou cancela, e nunca a própria viagem.
- O nome do solicitante vem de quem está logado, nunca do corpo da requisição.
- Os IDs dos pedidos são UUID, então ninguém consegue "chutar" o pedido de outra pessoa.
- Se dois administradores decidirem o mesmo pedido ao mesmo tempo, o banco trava o pedido e só a primeira decisão vale.

**Fiz o aviso sem travar a API.** A notificação vai para uma fila e só é enviada depois que a mudança foi salva de vez. Se o e-mail estiver lento, a resposta da API não espera.

**Escrevi testes para cada requisito** e rodo esses testes no MySQL, o mesmo banco usado em produção.

**Deixei tudo em português:** tabelas, colunas, rotas, mensagens e comentários. Só mantive em inglês o que é padrão do próprio Laravel.

**Fui um pouco além do pedido:**

- registro de quem aprovou ou cancelou cada pedido, e quando;
- cadastro da equipe pelo administrador;
- destaque e lembrete automático para pedidos parados há mais de 48 horas;
- um front-end simples em Vue;
- uma coleção do Postman pronta para usar.

---

## 2. Instalando as dependências

Não há nada para instalar à mão além do [Docker Desktop](https://docs.docker.com/get-docker/). Basta baixar o projeto:

```bash
git clone https://github.com/mauricio159/TesteBackEndOnfly.git
cd TesteBackEndOnfly
```

Na primeira vez que o projeto sobe, os próprios containers instalam tudo o que falta: as dependências do PHP (`composer install`), as do front-end (`npm install`) e as extensões do PHP.

---

## 3. Rodando o projeto com Docker

```bash
docker compose up -d --build                  # sobe tudo; a primeira vez leva alguns minutos
docker compose exec app php artisan db:seed   # cria usuários e pedidos de exemplo
```

Pronto. Na subida, o projeto também cria o `.env`, gera a chave da aplicação e cria as tabelas do banco sozinho.

| O que | Onde |
|---|---|
| API | http://localhost:8000/api/v1 |
| Front-end | http://localhost:5173 |
| E-mails enviados (Mailpit) | http://localhost:8025 |

**Usuários para testar.** Todos usam a senha `senha123`.

| E-mail | Quem é |
|---|---|
| `admin@viagens.test` | Carla, administradora |
| `diretor@viagens.test` | Diego, administrador (pode aprovar as viagens da Carla) |
| `ana@viagens.test` | Ana, colaboradora |
| `bruno@viagens.test` | Bruno, colaborador |

Para fazer o primeiro login pela API:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ana@viagens.test","senha":"senha123"}'
```

O token da resposta vai no header `Authorization: Bearer <token>` das próximas chamadas.

---

## 4. Configurando o ambiente

**Já vem tudo configurado.** Na primeira subida, o `.env` é criado a partir do `.env.example`, que já está ajustado para o Docker. Você só precisa mexer nele se quiser mudar algo. Os itens que mais costumam interessar são:

| Variável | Padrão | O que faz |
|---|---|---|
| `PEDIDO_ALERTA_APOS_HORAS` | `48` | Depois de quantas horas sem decisão um pedido é considerado atrasado |
| `PEDIDO_REPETIR_LEMBRETE_APOS_HORAS` | `24` | Intervalo mínimo entre dois lembretes do mesmo pedido |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso usado para saber o que é "hoje" |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `viagens_corporativas`, `viagens`, `segredo` | Acesso ao banco |
| `MAIL_HOST` | `mailpit` | Para onde vão os e-mails (em desenvolvimento, o Mailpit) |

Depois de mudar o `.env`, rode `docker compose exec app php artisan config:clear` e `docker compose restart queue scheduler`.

**Banco de dados.** O MySQL roda num container próprio e os dados continuam salvos mesmo se você desligar o Docker. Para abrir as tabelas num programa como o DBeaver ou o MySQL Workbench, use:

- host `localhost`, porta `3307`;
- banco `viagens_corporativas`;
- usuário `viagens` e senha `segredo`.

A porta é 3307, e não 3306, para não brigar com um MySQL que já esteja instalado na sua máquina.

Os testes usam outro banco, o `viagens_corporativas_teste`, que é criado sozinho. Rodar os testes nunca apaga os dados de exemplo.

---

## 5. Rodando os testes

```bash
docker compose exec app php artisan test
```

São **77 testes** cobrindo os requisitos do desafio:

- criar, consultar e listar pedidos com cada filtro;
- quem pode aprovar ou cancelar;
- a regra de que um pedido aprovado não pode ser cancelado;
- as validações de datas;
- o envio das notificações.

Também cobrem os extras.

Se preferir testar a API "na mão", importe no Postman o arquivo `postman/pedidos-viagem.postman_collection.json` e clique em **Run collection**. São 36 requisições que já guardam o token e o ID do pedido sozinhas. O passo a passo está em [postman/README.md](postman/README.md).

---

## 6. Informações adicionais

**Principais rotas da API** (todas começam com `/api/v1`):

| Rota | O que faz |
|---|---|
| `POST /auth/login` | Faz login e devolve o token |
| `POST /pedidos-viagem` | Cria um pedido (`destino`, `data_ida`, `data_volta`) |
| `GET /pedidos-viagem` | Lista os pedidos. Filtros: `status`, `destino`, `criado_de`/`criado_ate`, `viagem_de`/`viagem_ate`, `atrasados=1` |
| `GET /pedidos-viagem/{id}` | Consulta um pedido |
| `PATCH /pedidos-viagem/{id}/status` | Aprova ou cancela (`status`: `aprovado` ou `cancelado`). Só administrador |
| `GET /notificacoes` | Avisos de quem está logado |
| `GET` e `POST /usuarios` | Lista e cadastra a equipe. Só administrador |

**Como as regras funcionam, em resumo:**

- Todo pedido começa como **solicitado** e pode virar **aprovado** ou **cancelado**. Depois disso, não muda mais.
- Só administrador decide, e nunca sobre a própria viagem.
- A data de ida não pode estar no passado, e a volta não pode ser antes da ida.
- Ao aprovar ou cancelar, o solicitante recebe um e-mail e um aviso no sistema dizendo quem decidiu.
- Não existe cadastro público: quem cadastra as pessoas é o administrador.
- Pedido sem decisão há mais de 48 horas aparece destacado, e os administradores recebem um lembrete de hora em hora até alguém decidir.

**Dicas rápidas:**

| Situação | O que fazer |
|---|---|
| Ver os lembretes sem esperar uma hora | `docker compose exec app php artisan pedidos:lembrar-pendentes` |
| Mudei código PHP e o e-mail não mudou | `docker compose exec app php artisan queue:restart` (a fila guarda o código em memória) |
| Começar do zero | `docker compose down -v`, depois os dois comandos da seção 3 |
| Alguma porta já está em uso | Troque a porta em `compose.yaml` (por exemplo, `"8080:80"` no `nginx`) |

**Duas observações:**

- As senhas que aparecem aqui (`segredo`, `senha123`) servem só para rodar o projeto localmente. Em produção, viriam de um lugar seguro.
- No Windows, deixei as dependências do PHP dentro do Docker, e não na pasta do projeto. Isso fez cada requisição cair de cerca de 3 segundos para cerca de 40 milissegundos.

Quer ver mais a fundo? Em [docs/DETALHES.md](docs/DETALHES.md) estão:

- a API completa, com exemplos de resposta;
- todas as decisões técnicas e o porquê de cada uma;
- a organização das pastas;
- o front-end e a lista completa do que os testes cobrem.
