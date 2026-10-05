# Contribuindo

Este guia explica como preparar o ambiente, quais padrões seguir e o que verificar antes de entregar uma alteração.

## Ambiente de desenvolvimento

1. Siga o [Início rápido](README.md#início-rápido) para ter o sistema rodando localmente.
2. Instale o Node.js 18+ e as dependências de build:

   ```bash
   npm install
   npm run watch:css   # deixe rodando enquanto altera as telas
   ```

3. Crie pelo menos um usuário de cada perfil (Hardware, ERP e administrador) para testar as permissões.

## Padrões de código

As convenções completas estão em [docs/ARQUITETURA.md](docs/ARQUITETURA.md#convenções-de-código). As principais:

- **Páginas finas.** Regras, validações e SQL ficam em `src/<módulo>.php`. A página só autoriza, chama o domínio e renderiza.
- **Tipagem estrita.** `declare(strict_types=1)` e tipos em parâmetros e retornos.
- **SQL sempre parametrizado**, com os helpers de `src/db.php`. Use placeholders posicionais (`?`).
- **Múltiplas escritas** dentro de `transacao()`.
- **Toda alteração de dados** chama `auditar()`. Edições registram antes e depois com `diferencas()`.
- **Toda saída dinâmica** passa por `e()`.
- **Erros de regra** são `RuntimeException` com mensagem pronta para o usuário. Validações de formulário devolvem `array` campo => mensagem.
- **Status** são referenciados pelo papel (`STATUS_*`), nunca por id ou nome.
- **Comentários** só para restrições e decisões que o código não expressa. Nomes claros dispensam comentários.
- **Estilo:** 4 espaços em PHP e 2 em CSS/JSON (veja `.editorconfig`). Identificadores e mensagens em português.

## Receitas

### Nova página

```php
<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_setor('hardware');

if (is_post()) {
    csrf_verificar();
    try {
        modulo_acao(/* ... */);
        flash('sucesso', 'Mensagem de sucesso.');
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
    }
    redirect('pagina.php');
}

render_header('Título da página', 'chave-do-menu');
?>
<form method="post" class="card p-4 sm:p-6">
    <?= csrf_field() ?>
    <!-- campos -->
</form>
<?php render_footer(); ?>
```

Para aparecer no menu, acrescente o item à constante correspondente em `templates/layout.php` (`MENU_HARDWARE`, `MENU_ERP` ou `MENU_ADMIN`).

### Nova listagem com filtros

Use `Filtro` e `consulta_paginada()` de `src/listagem.php` e `render_paginacao()` de `templates/componentes.php`. Veja o exemplo em [ARQUITETURA.md](docs/ARQUITETURA.md#listagens-filtradas-e-paginadas-srclistagemphp).

### Alteração no banco

Atualize `database/schema.sql` **e** acrescente uma migração protegida em `database/setup.php`. Veja [BANCO_DE_DADOS.md](docs/BANCO_DE_DADOS.md#como-adicionar-uma-migração).

### Nova classe visual

Se uma combinação de classes Tailwind se repete em várias telas, crie uma classe de componente em `src/input.css` e rode `npm run build:css`. O `app.css` gerado deve ser versionado junto.

## Checklist antes de entregar

- [ ] `php -l` sem erros nos arquivos alterados:

  ```powershell
  Get-ChildItem -Recurse -Include *.php src,public,templates,config,database | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName } | Select-String -NotMatch 'No syntax errors'
  ```

- [ ] CSS recompilado (`npm run build:css`), se houve mudança de classes.
- [ ] Testado com os três perfis (Hardware, ERP e administrador), incluindo o acesso negado (403) onde aplicável.
- [ ] Fluxos afetados testados de ponta a ponta, com mensagens de erro e de sucesso.
- [ ] Nenhuma entrada nova no `C:\xampp\apache\logs\error.log`.
- [ ] As ações novas aparecem em **Logs do sistema** com descrição clara.
- [ ] Migração (se houver) testada numa cópia do banco e reexecutada sem erro.
- [ ] Documentação atualizada (README e `docs/`) quando o comportamento muda.
- [ ] Nenhum dado sensível no commit (`config/database.php`, backups, dumps).

## Commits

Mensagens curtas, no imperativo, com prefixo do tipo de mudança:

| Prefixo | Uso |
| --- | --- |
| `feat:` | Nova funcionalidade |
| `fix:` | Correção de defeito |
| `refactor:` | Mudança interna sem alterar comportamento |
| `docs:` | Documentação |
| `style:` | Visual ou CSS, sem mudança de regra |
| `chore:` | Build, dependências, configuração |

Exemplo: `feat: permite filtrar OS por técnico responsável`.
