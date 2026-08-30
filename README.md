# Mia Assistente

Sistema web mobile-first para gestão financeira e de atividades, com administração, categorias personalizadas e lançamentos por texto ou áudio via Telegram.

## Executar com Docker

Pré-requisito: Docker Desktop em execução.

```bash
docker compose up --build -d
```

Acesse [http://localhost:8088](http://localhost:8088).

Contas iniciais:

- Administrador: `admin@mia.local` / `Mia@12345`
- Cliente de demonstração: `cliente@mia.local` / `Mia@12345`

O banco PostgreSQL é migrado e populado automaticamente quando o container da aplicação inicia. Os dados persistem no volume `mia_postgres_data`.

## Serviços

- `app`: Laravel 13, interface web, autenticação, regras de negócio e webhook do Telegram.
- `telegram-worker`: processo permanente que recebe mensagens por polling no ambiente local.
- `cognition`: FastAPI, parser local e conectores opcionais de OpenAI/Gemini.
- `postgres`: PostgreSQL 17.

## Funcionalidades

- Cadastro de cliente em três etapas: dados, código do Telegram e senha.
- Dashboard mensal com saldo, entradas, saídas e atividades.
- CRUD financeiro, totais mensais e agrupamento por categoria.
- Quadro de atividades com prioridades, status e continuidade entre meses.
- Categorias globais e personalizadas para entradas, saídas e atividades.
- Área `/admin` com métricas, usuários, clientes, categorias e configurações.
- Webhook do Telegram em `/telegram/webhook` para mensagens e áudios.
- Autenticação de mensagens pelo `telegram_user_id`, proteção idempotente e detecção de possíveis duplicidades.
- Confirmação com botões para valores acima de R$ 100 ou confiança entre 0,70 e 0,89; abaixo de 0,70 nada é gravado.
- Chaves sensíveis criptografadas no PostgreSQL.
- Gemini como provedor principal para interpretar texto e áudio, com OpenAI e parser local como contingências.
- API autenticada para AlugaPro e Dashpay lançarem recebimentos idempotentes para clientes ativos.
- Automação do iPhone para encaminhar alertas bancários por SMS/e-mail ao mesmo fluxo de interpretação e confirmação do Telegram.

## API de recebimentos externos

Configure uma chave com pelo menos 32 caracteres em **Admin > Configurações > API de recebimentos**. Como alternativa, defina `ALUGAPRO_FINANCE_API_KEY` ou `DASHPAY_FINANCE_API_KEY` no ambiente da aplicação. Cada sistema deve usar apenas a sua própria chave no cabeçalho `Authorization: Bearer`.

O `cliente_id` aparece em **Admin > Clientes**, abaixo do e-mail de cada cliente.

### Criar recebimento

`POST /api/v1/clientes/{cliente_id}/recebimentos`

```bash
curl -X POST http://localhost:8088/api/v1/clientes/2/recebimentos \
  -H "Authorization: Bearer SUA_CHAVE" \
  -H "Content-Type: application/json" \
  -d '{
    "external_id": "pagamento-87421",
    "title": "Aluguel recebido",
    "description": "Pagamento da competência 08/2026",
    "amount": 1850.00,
    "occurred_on": "2026-08-28"
  }'
```

`external_id`, `title`, `description` e `amount` são obrigatórios. `occurred_on` é opcional e assume a data atual. O lançamento é sempre criado como entrada (`income`). A primeira requisição retorna `201`; uma repetição idêntica retorna `200` sem duplicar; a reutilização do mesmo `external_id` com dados diferentes retorna `409`.

### Consultar recebimento

`GET /api/v1/clientes/{cliente_id}/recebimentos/{external_id}`

A consulta só encontra lançamentos criados para aquele cliente pela mesma integração autenticada. Requisições sem chave válida retornam `401`; dados inválidos retornam `422`; clientes ou recebimentos inexistentes retornam `404`. O limite padrão é de 120 requisições por minuto por chave.

## Automação bancária no iPhone

Depois de conectar o Telegram, abra **Telegram > Automação bancária no iPhone** na Mia e gere uma chave pessoal. No app Atalhos do iPhone, crie uma Automação Pessoal com o gatilho **Mensagem**, restrinja o remetente ao número oficial do banco e selecione **Executar imediatamente**. A automação deve gerar um UUID e usar **Obter Conteúdo de URL** para enviar um `POST` a:

`/api/v1/iphone/eventos-bancarios`

Use `Authorization: Bearer SUA_CHAVE` e um corpo JSON como:

```json
{
  "event_id": "UUID-GERADO-PELO-ATALHO",
  "text": "Compra aprovada no valor de R$ 42,90 em Padaria Central",
  "source": "sms",
  "sender": "Banco Exemplo"
}
```

`event_id`, `text` e `source` são obrigatórios. `source` aceita `sms`, `email`, `wallet` ou `manual`; `sender` e `received_at` são opcionais. A chave identifica o cliente, e cada `event_id` só é processado uma vez. O texto original não é armazenado: a Mia conserva somente seu hash para conferir reenvios. Mensagens que pareçam conter OTP, token, senha, CVV ou código de segurança são descartadas antes de qualquer chamada à IA.

O iOS permite automações disparadas por SMS e e-mail, mas não oferece um gatilho genérico para ler o conteúdo das notificações push de outros aplicativos. Para bancos que notificam apenas pelo app, use SMS/e-mail, uma automação de transação da Carteira quando disponível ou uma integração própria via Open Finance. Em produção, o endpoint deve estar publicado em HTTPS; em desenvolvimento, `localhost` no Atalho aponta para o próprio iPhone, não para o computador.

## Configurar Telegram e IA

1. Entre em `/admin/configuracoes` com a conta administrativa.
2. Informe o token do bot, usuário do bot e um secret forte para o webhook.
3. No computador local, selecione **Local — polling permanente**. O serviço `telegram-worker` inicia e reinicia automaticamente com o Docker.
4. Em produção, selecione **Produção — webhook HTTPS** e informe `https://seu-dominio/telegram/webhook`. Ao salvar, a Mia registra o webhook e o secret automaticamente no Telegram.
5. Informe uma API key Gemini e mantenha o provedor principal como **Google Gemini**. O serviço envia textos e áudios à API do Gemini e usa OpenAI como contingência quando uma chave OpenAI também estiver configurada.

As chaves são criptografadas no banco. Para trocar uma chave, informe a nova; para preservar a atual, deixe o campo secreto vazio.

No cadastro, o cliente deve clicar em **Abrir bot**, tocar em **Iniciar** e aguardar a resposta. Essa primeira mensagem entrega à Mia o `telegram_user_id`, vincula a conta e faz o bot responder com o código de confirmação. O Telegram não permite que um bot encontre o ID pelo `@usuário` ou inicie a conversa sozinho.

Polling e webhook são mutuamente exclusivos para um mesmo bot. Se local e produção precisarem funcionar simultaneamente, crie um segundo bot de desenvolvimento com outro token.

## Comandos úteis

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f telegram-worker
docker compose run --rm -e APP_ENV=testing --entrypoint sh app -lc "cp .env.example .env && composer install --no-interaction && php artisan test"
docker compose down
```

Para apagar também o banco local e recomeçar do zero, use `docker compose down -v` conscientemente.
