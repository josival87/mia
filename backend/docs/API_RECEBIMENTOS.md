# Manual da API de recebimentos da Mia

Versão da API: `v1`  
Ambiente: produção  
URL-base: `https://jbmj.io/mia`

## 1. Finalidade

Esta API permite que o AlugaPro e o DashPay criem lançamentos de recebimento para clientes ativos da Mia.

Todo lançamento criado por esta API é registrado como uma entrada financeira:

```text
type = income
```

A API não possui, nesta versão, endpoints para despesas, alteração ou exclusão de lançamentos.

## 2. Preparação da integração

### 2.1 Configurar as chaves na Mia

1. Entre na administração da Mia.
2. Abra `https://jbmj.io/mia/admin/configuracoes`.
3. Na seção **API de recebimentos**, informe uma chave para o AlugaPro e outra para o DashPay.
4. Salve as configurações.

As chaves são diferentes porque a Mia identifica automaticamente o sistema de origem pelo token recebido. Não envie um campo `source` no JSON.

### 2.2 Encontrar o ID do cliente

Abra `https://jbmj.io/mia/admin/clientes`. Abaixo do nome de cada cliente é exibido o valor **ID da API**.

Esse número deve substituir `{cliente_id}` nas URLs. A API somente aceita clientes com perfil de cliente e status ativo.

### 2.3 Autenticação

Todas as requisições devem usar HTTPS e enviar a chave do respectivo sistema no cabeçalho HTTP:

```http
Authorization: Bearer SUA_CHAVE
Accept: application/json
Content-Type: application/json
```

No cabeçalho, `Bearer` faz parte do formato de autenticação e deve ser seguido por um espaço e pela chave. No painel da Mia, por outro lado, cadastre somente o valor da chave.

Não coloque a chave no corpo, na URL, em código JavaScript executado no navegador ou em logs. A integração deve ser feita entre os servidores dos sistemas.

## 3. Criar um recebimento

### Endpoint

```http
POST https://jbmj.io/mia/api/v1/clientes/{cliente_id}/recebimentos
```

Exemplo para o cliente de ID `42`:

```http
POST https://jbmj.io/mia/api/v1/clientes/42/recebimentos
```

### Corpo da requisição

Envie um objeto JSON com os seguintes campos:

| Campo | Tipo | Obrigatório | Regra |
|---|---|---:|---|
| `external_id` | string | Sim | Identificador único do lançamento no sistema de origem. Máximo de 120 caracteres. Deve começar com letra ou número e pode conter somente letras, números, `.`, `_`, `-` e `:`. |
| `title` | string | Sim | Título do lançamento. Máximo de 180 caracteres. |
| `description` | string | Sim | Descrição do lançamento. Máximo de 255 caracteres. |
| `amount` | número ou string numérica | Sim | Valor positivo de `0.01` até `999999999999.99`, com no máximo duas casas decimais. Use ponto como separador decimal e não envie símbolo de moeda nem separador de milhar. |
| `occurred_on` | string | Não | Data do recebimento no formato `AAAA-MM-DD`. Se omitida, a Mia usa a data atual do servidor. Recomenda-se enviá-la sempre. |

Exemplo:

```json
{
  "external_id": "aluguel:contrato-9842:2026-08",
  "title": "Aluguel recebido",
  "description": "Pagamento da competência 08/2026",
  "amount": "1850.00",
  "occurred_on": "2026-08-28"
}
```

Não envie valores brasileiros formatados como `1.850,00`. O formato correto é `1850.00`.

### Resposta de criação — HTTP 201

Quando o lançamento é criado, a API retorna `201 Created`:

```json
{
  "data": {
    "id": 731,
    "client_id": 42,
    "external_id": "aluguel:contrato-9842:2026-08",
    "type": "income",
    "title": "Aluguel recebido",
    "description": "Pagamento da competência 08/2026",
    "amount": "1850.00",
    "occurred_on": "2026-08-28",
    "source": "alugapro",
    "created_at": "2026-08-28T18:45:12+00:00"
  },
  "meta": {
    "created": true
  }
}
```

O campo `source` será `alugapro` ou `dashpay`, conforme a chave Bearer utilizada.

## 4. Reenvio e prevenção de duplicidade

O campo `external_id` funciona como chave de idempotência. Ele é único dentro da combinação **sistema de origem + cliente**.

- Em uma repetição, envie o mesmo `external_id` e exatamente o mesmo conteúdo da requisição original.
- Não gere outro `external_id` apenas porque houve timeout ou falha de rede.
- Recomenda-se usar o identificador imutável do pagamento, fatura, repasse ou contrato no sistema de origem.
- Envie `occurred_on` explicitamente. Se ele for omitido, uma repetição feita em outro dia poderá usar uma data diferente e resultar em conflito.

### Repetição idêntica — HTTP 200

Se o lançamento já existir e os dados forem iguais, nenhum novo registro é criado. A API retorna o mesmo objeto com:

```json
{
  "data": {
    "id": 731,
    "client_id": 42,
    "external_id": "aluguel:contrato-9842:2026-08",
    "type": "income",
    "title": "Aluguel recebido",
    "description": "Pagamento da competência 08/2026",
    "amount": "1850.00",
    "occurred_on": "2026-08-28",
    "source": "alugapro",
    "created_at": "2026-08-28T18:45:12+00:00"
  },
  "meta": {
    "created": false
  }
}
```

Tanto `201` com `meta.created = true` quanto `200` com `meta.created = false` representam sucesso.

### Mesmo identificador com dados diferentes — HTTP 409

Se o `external_id` já tiver sido usado e título, descrição, valor ou data forem diferentes, a API rejeita a requisição:

```json
{
  "message": "O external_id já foi usado com dados diferentes.",
  "errors": {
    "external_id": [
      "Use o mesmo conteúdo da requisição original ou informe outro external_id."
    ]
  }
}
```

Não tente corrigir um lançamento existente reenviando o mesmo `external_id` com outros dados. Como a versão atual não oferece edição, a divergência deve ser tratada operacionalmente na Mia ou enviada como um novo evento com outro identificador, conforme a regra de negócio.

## 5. Consultar um recebimento pelo identificador externo

Use este endpoint para confirmar o estado de uma operação após timeout ou resposta perdida:

```http
GET https://jbmj.io/mia/api/v1/clientes/{cliente_id}/recebimentos/{external_id}
```

Exemplo:

```http
GET https://jbmj.io/mia/api/v1/clientes/42/recebimentos/aluguel:contrato-9842:2026-08
```

Envie os cabeçalhos `Authorization` e `Accept`. Não há corpo na requisição GET.

### Resposta encontrada — HTTP 200

```json
{
  "data": {
    "id": 731,
    "client_id": 42,
    "external_id": "aluguel:contrato-9842:2026-08",
    "type": "income",
    "title": "Aluguel recebido",
    "description": "Pagamento da competência 08/2026",
    "amount": "1850.00",
    "occurred_on": "2026-08-28",
    "source": "alugapro",
    "created_at": "2026-08-28T18:45:12+00:00"
  }
}
```

Cada integração enxerga apenas os próprios lançamentos. Um recebimento criado com a chave do AlugaPro não será encontrado em uma consulta autenticada com a chave do DashPay, e vice-versa.

## 6. Códigos de resposta

| HTTP | Significado | Como tratar |
|---:|---|---|
| `200` | Consulta bem-sucedida ou repetição idêntica de um POST já processado. | Considere a operação concluída. Em POST, confira `meta.created`. |
| `201` | Novo recebimento criado. | Considere a operação concluída e guarde `data.id`. |
| `401` | Chave ausente ou inválida. | Confira o cabeçalho e a chave cadastrada para o sistema. Não repita automaticamente sem corrigir a credencial. |
| `404` | Cliente ou recebimento não encontrado. | Confira o ID do cliente, o `external_id` e se a consulta usa a mesma chave que criou o lançamento. |
| `409` | O `external_id` já existe com conteúdo diferente. | Não repita. Investigue a divergência dos dados. |
| `422` | Cliente inativo ou dados inválidos. | Corrija o cadastro do cliente ou os campos informados antes de reenviar. |
| `429` | Limite de requisições excedido. | Aguarde o período indicado por `Retry-After` e tente novamente com espera progressiva. |
| `500` | Falha inesperada no servidor. | Consulte pelo `external_id` e, se não encontrado, repita o mesmo POST com espera progressiva. |

### Chave ausente ou inválida — HTTP 401

```json
{
  "message": "Chave de integração ausente ou inválida."
}
```

### Cliente inexistente — HTTP 404

```json
{
  "message": "Cliente não encontrado."
}
```

### Cliente inativo — HTTP 422

```json
{
  "message": "O cliente está inativo."
}
```

### Dados inválidos — HTTP 422

Erros de validação retornam uma mensagem geral e um mapa `errors`. Cada campo pode ter uma ou mais mensagens:

```json
{
  "message": "The external id field format is invalid. (and 2 more errors)",
  "errors": {
    "external_id": [
      "O external_id deve começar com letra ou número e usar apenas letras, números, ponto, hífen, sublinhado ou dois-pontos."
    ],
    "amount": [
      "O valor deve ter no máximo duas casas decimais."
    ],
    "occurred_on": [
      "A data deve estar no formato AAAA-MM-DD."
    ]
  }
}
```

O texto da mensagem geral pode variar conforme o idioma configurado no servidor. A integração deve tomar decisões pelo status HTTP e pelas chaves do objeto `errors`, não comparando textos literalmente.

## 7. Limites de uso

A API aplica simultaneamente os seguintes limites:

- até 120 requisições por minuto por chave;
- até 300 requisições por minuto por endereço IP.

Ao receber `429 Too Many Requests`, respeite o cabeçalho `Retry-After`. Evite disparar muitas tentativas paralelas para o mesmo lançamento.

## 8. Exemplos de implementação

### cURL

```bash
curl --request POST \
  "https://jbmj.io/mia/api/v1/clientes/42/recebimentos" \
  --header "Authorization: Bearer SUA_CHAVE" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "external_id": "aluguel:contrato-9842:2026-08",
    "title": "Aluguel recebido",
    "description": "Pagamento da competência 08/2026",
    "amount": "1850.00",
    "occurred_on": "2026-08-28"
  }'
```

Consulta:

```bash
curl --request GET \
  "https://jbmj.io/mia/api/v1/clientes/42/recebimentos/aluguel:contrato-9842:2026-08" \
  --header "Authorization: Bearer SUA_CHAVE" \
  --header "Accept: application/json"
```

### PHP com o cliente HTTP do Laravel

```php
use Illuminate\Support\Facades\Http;

$baseUrl = 'https://jbmj.io/mia';
$clientId = 42;
$token = config('services.mia.token');

$response = Http::withToken($token)
    ->acceptJson()
    ->timeout(15)
    ->post("{$baseUrl}/api/v1/clientes/{$clientId}/recebimentos", [
        'external_id' => 'aluguel:contrato-9842:2026-08',
        'title' => 'Aluguel recebido',
        'description' => 'Pagamento da competência 08/2026',
        'amount' => '1850.00',
        'occurred_on' => '2026-08-28',
    ]);

if (in_array($response->status(), [200, 201], true)) {
    $miaId = $response->json('data.id');
    $wasCreated = $response->json('meta.created');
} else {
    $status = $response->status();
    $error = $response->json();
}
```

Guarde a chave em uma variável de ambiente do sistema de origem, nunca diretamente no repositório:

```dotenv
MIA_API_URL=https://jbmj.io/mia
MIA_API_TOKEN=chave_secreta_do_respectivo_sistema
```

## 9. Estratégia recomendada de envio

1. Gere ou recupere um `external_id` estável no sistema de origem.
2. Grave localmente o conteúdo que será enviado, incluindo `occurred_on`.
3. Faça o POST com timeout de conexão e leitura.
4. Trate `200` e `201` como sucesso.
5. Em timeout ou erro `500`, consulte o endpoint GET com o mesmo `external_id` antes de repetir.
6. Se ainda não existir, repita exatamente o mesmo POST e o mesmo `external_id`.
7. Em `401`, `404`, `409` ou `422`, não faça repetição automática sem corrigir a causa.
8. Em `429`, aguarde `Retry-After` e aplique espera progressiva.

## 10. Checklist de homologação

- [ ] A chave correta foi cadastrada na Mia e no sistema de origem.
- [ ] O sistema envia `Authorization: Bearer ...` via HTTPS.
- [ ] O `cliente_id` corresponde ao **ID da API** exibido na administração.
- [ ] O cliente usado no teste está ativo.
- [ ] O valor usa ponto e no máximo duas casas decimais.
- [ ] A data usa o formato `AAAA-MM-DD`.
- [ ] O primeiro POST retorna `201` e `meta.created = true`.
- [ ] O mesmo POST, repetido, retorna `200` e `meta.created = false`.
- [ ] Uma consulta GET retorna os dados do lançamento.
- [ ] As chaves e os corpos das requisições não aparecem em logs públicos.

