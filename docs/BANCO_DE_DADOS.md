# Banco de Dados

MySQL ou MariaDB com engine **InnoDB** (transações e chaves estrangeiras) e charset **`utf8mb4_unicode_ci`**. A estrutura completa está em [`database/schema.sql`](../database/schema.sql).

## Diagrama entidade-relacionamento

```mermaid
erDiagram
    usuarios ||--o{ manutencoes : "técnico de"
    usuarios ||--o{ movimentacoes : registra
    usuarios ||--o{ logs_acesso : gera
    usuarios ||--o{ logs_sistema : gera
    usuarios ||--o{ atualizacoes : publica
    usuarios ||--o{ atualizacoes_vistas : visualiza
    equipamentos ||--o{ manutencoes : possui
    status_manutencao ||--o{ manutencoes : "status atual"
    status_manutencao ||--o{ movimentacoes : "anterior / novo"
    manutencoes ||--o{ movimentacoes : trajetória
    manutencoes ||--o{ pecas_trocadas : usa
    atualizacoes ||--o{ atualizacoes_vistas : "vista por"

    usuarios {
        int id PK
        varchar login UK
        varchar senha_hash
        enum setor "hardware | erp"
        bool admin
        bool ativo
        int sessao_versao
    }
    equipamentos {
        int id PK
        varchar codigo UK "patrimônio ou NS"
        enum tipo_codigo "patrimonio | ns"
        varchar tipo
        varchar marca
        varchar modelo
        varchar setor_origem
        varchar responsavel
    }
    status_manutencao {
        int id PK
        varchar nome UK
        varchar cor
        int ordem
        enum sistema UK "inicial | enviado_erp | recebido_erp"
        bool encerra
        bool ativo
    }
    manutencoes {
        int id PK "número da OS"
        int equipamento_id FK
        int status_id FK
        int tecnico_id FK
        text defeito_relatado
        text servico_executado
        datetime aberta_em
        datetime concluida_em
    }
    movimentacoes {
        int id PK
        int manutencao_id FK
        int status_anterior_id FK
        int status_novo_id FK
        int usuario_id FK
        enum setor
        text observacao
        datetime editado_em
        int editado_por FK
    }
    pecas_trocadas {
        int id PK
        int manutencao_id FK
        varchar descricao
        int quantidade
    }
    logs_acesso {
        bigint id PK
        int usuario_id FK
        varchar login
        enum evento "login | logout | falha"
        varchar ip
    }
    logs_sistema {
        bigint id PK
        int usuario_id FK
        varchar usuario_nome
        varchar categoria
        varchar acao
        int referencia_id
        text detalhes "JSON antes/depois"
    }
    atualizacoes {
        int id PK
        varchar titulo
        varchar versao
        bool publicado
        datetime publicado_em
    }
    atualizacoes_vistas {
        int usuario_id PK
        int atualizacao_id PK
    }
    configuracoes {
        varchar chave PK
        varchar valor
    }
```

## Tabelas

| Tabela | Finalidade | Observações |
| --- | --- | --- |
| `usuarios` | Contas de acesso | `senha_hash` usa `password_hash` (bcrypt). Incrementar `sessao_versao` derruba as sessões abertas do usuário |
| `equipamentos` | Aparelhos identificados por patrimônio ou número de série | `codigo` é único e normalizado em maiúsculas, sem espaços |
| `status_manutencao` | Status configuráveis da OS | `sistema` marca os três papéis fixos (únicos). `encerra` define se a OS está em andamento |
| `manutencoes` | Ordens de serviço | O `id` é o número da OS. `concluida_em` é preenchida ao encerrar e limpa ao reabrir |
| `movimentacoes` | Trajetória: trocas de status e observações | Observação avulsa: `status_anterior_id = status_novo_id` |
| `pecas_trocadas` | Peças usadas em cada OS | Excluídas em cascata com a OS |
| `logs_acesso` | Logins, saídas e falhas de autenticação | `login` guarda o texto digitado, inclusive de usuários inexistentes. É a base do bloqueio de força bruta |
| `logs_sistema` | Auditoria de todas as ações | `usuario_nome` guarda o nome da época, para o log sobreviver à exclusão do usuário. `detalhes` guarda o JSON com antes/depois |
| `atualizacoes` | Anúncios de novas versões | `publicado_em` marca o início da janela de 24 horas do popup |
| `atualizacoes_vistas` | Quem já viu cada anúncio | Chave composta, que garante o aviso pós-login uma única vez |
| `configuracoes` | Parâmetros editáveis pela interface | Chave/valor: tamanho e título da etiqueta |

## Integridade referencial

| Relação | Ao excluir o pai |
| --- | --- |
| `manutencoes` → `equipamentos`, `status_manutencao` | Bloqueado (`RESTRICT`) |
| `pecas_trocadas`, `movimentacoes` → `manutencoes` | Cascata |
| `manutencoes.tecnico_id`, `movimentacoes.editado_por`, logs e `atualizacoes.criado_por` → `usuarios` | `SET NULL` |
| `movimentacoes.usuario_id` → `usuarios` | Bloqueado. Por isso, usuário com histórico só pode ser desativado |
| `atualizacoes_vistas` → `usuarios`, `atualizacoes` | Cascata |

## Índices

Além das chaves primárias, únicas e estrangeiras, há índices para as consultas mais frequentes:

- `manutencoes (aberta_em)`, `movimentacoes (criado_em)`: listagens por período e dashboard.
- `equipamentos (tipo)`, `equipamentos (setor_origem)`: filtros da listagem.
- `logs_acesso (criado_em)`, `logs_acesso (usuario_id, criado_em)`: filtros dos logs e cálculo do bloqueio.
- `logs_sistema (criado_em)`, `(categoria, criado_em)`, `(usuario_id, criado_em)`: filtros dos logs.
- `atualizacoes (publicado, publicado_em)`: anúncio em destaque.

## Dados iniciais

O `schema.sql` insere, com `INSERT IGNORE` (seguro para reexecução):

- os oito status padrão, incluindo os três de sistema;
- as configurações padrão da etiqueta (40 x 25 mm, título "Hardware").

O `setup.php` cria o usuário `admin` somente se a tabela `usuarios` estiver vazia.

## Migrações

O `database/setup.php` é a ferramenta de instalação **e** de migração. Ele:

1. cria o banco, se não existir;
2. executa o `schema.sql` (todas as instruções são `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`);
3. aplica as migrações incrementais, cada uma protegida por uma verificação de existência em `information_schema`;
4. cria o administrador inicial, se necessário.

Reexecutá-lo é sempre seguro.

### Como adicionar uma migração

1. Atualize o `CREATE TABLE` em `schema.sql`, para que instalações novas já nasçam com a estrutura final.
2. Acrescente ao `setup.php` um bloco protegido, para que instalações existentes recebam a alteração:

```php
if (!coluna_existe($pdo, 'equipamentos', 'localizacao')) {
    $pdo->exec('ALTER TABLE equipamentos ADD localizacao VARCHAR(120) NULL AFTER responsavel');
    echo "Atualização: localização adicionada aos equipamentos.\n";
}
```

3. Faça um backup antes de rodar em produção (veja [OPERACAO.md](OPERACAO.md)).
