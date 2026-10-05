# Operação

Guia para quem instala, mantém e dá suporte ao sistema no dia a dia.

## Implantação

### Requisitos

| Componente | Versão |
| --- | --- |
| PHP | 8.1 ou superior, com as extensões `pdo_mysql` e `mbstring` (já incluídas no XAMPP) |
| MySQL / MariaDB | MySQL 5.7+ ou MariaDB 10.4+ |
| Apache | 2.4 com `AllowOverride All` no diretório do projeto |
| Navegador | Chrome, Edge ou Firefox atualizados |

### Instalação em um servidor novo

1. Instale o XAMPP e ligue **Apache** e **MySQL**.
2. Copie o projeto para `C:\xampp\htdocs\Control` (via `git clone` ou cópia da pasta).
3. Crie `config/database.php` a partir de `config/database.example.php` e informe as credenciais do MySQL.
4. Revise `config/app.php`: `base_url` (se a pasta não for `/Control`), `timezone` e o caminho do `mysqldump`.
5. Rode o setup:

   ```powershell
   C:\xampp\php\php.exe database\setup.php "SenhaInicialDoAdmin"
   ```

6. Acesse `http://servidor/Control/`, entre como `admin` e cadastre os usuários em **Usuários e setores**.
7. Percorra o [checklist de segurança](SEGURANCA.md#checklist-de-implantação).

### Usuário MySQL dedicado (recomendado)

```sql
CREATE USER 'control'@'localhost' IDENTIFIED BY 'troque-esta-senha';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES, SHOW VIEW, TRIGGER
    ON control.* TO 'control'@'localhost';
FLUSH PRIVILEGES;
```

Depois, atualize `user` e `password` em `config/database.php`. Na primeira instalação, o `setup.php` precisa de permissão para criar o banco: rode-o com um usuário administrativo ou crie o banco antes.

## Atualização de versão

```powershell
# 1. Backup (pela tela Backup ou pelo comando da seção de backup)
# 2. Código novo
git pull
# 3. Migrações pendentes (seguro reexecutar)
C:\xampp\php\php.exe database\setup.php
```

- O CSS compilado é versionado, então não é preciso Node.js no servidor.
- Os arquivos estáticos recebem `?v=<data de modificação>` na URL, então os navegadores carregam a versão nova sem limpar o cache.
- Para avisar os usuários sobre a novidade, publique um anúncio em **Anunciar atualizações**.

## Backup e restauração

### Gerar

- **Pela interface:** em **Backup**, menu do administrador, clique em **Fazer backup agora**.
- O arquivo é salvo em `Backup/backup_control_AAAA-MM-DD_HH-MM-SS.sql`, com `--single-transaction`, sem travar o uso do sistema.
- São mantidos os **7 mais recentes**: ao gerar um novo, o mais antigo é apagado.
- O backup só é aceito se o `mysqldump` terminar com sucesso. Arquivos incompletos são descartados.
- Gerar e baixar backups fica registrado nos **Logs do sistema**.

> Os backups ficam no mesmo disco do servidor. Baixe uma cópia periodicamente e guarde-a em outro local.

### Restaurar

A restauração **substitui todos os dados atuais**. Antes de restaurar, gere um backup do estado atual.

```powershell
C:\xampp\mysql\bin\mysql.exe -u root control < Backup\backup_control_AAAA-MM-DD_HH-MM-SS.sql
```

Para restaurar em outro servidor, crie o banco vazio antes (`CREATE DATABASE control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`) e depois rode `setup.php` para aplicar migrações que o backup ainda não tenha.

### Agendamento (opcional)

Para backups automáticos, agende no **Agendador de Tarefas do Windows** um script com o `mysqldump` usando o mesmo formato de nome. A tela **Backup** lista e aplica a retenção a qualquer arquivo `backup_control_*.sql` da pasta.

## Etiquetas e impressora térmica

- Código de barras **Code128** (letras e números), gerado no navegador pelo JsBarcode local, sem internet.
- Tamanho e título configuráveis em **Configurações**. O padrão é 40 x 25 mm; para números de série longos, use 50 x 30 mm.
- Na janela de impressão, escolha a impressora térmica, margens **Nenhuma** e escala **100%**.
- Configure no driver da impressora o mesmo tamanho de papel definido no sistema.
- Modelos testados ou sugeridos: Elgin L42 Pro, Zebra ZD220 e ZD230.

## Leitor de código de barras

Leitores USB funcionam como teclado. Nas telas **Confirmar chegada** (ERP) e **Abrir OS**, o campo já vem focado: ao bipar, o código é digitado e o Enter envia a consulta. A busca do cabeçalho também aceita a leitura: basta clicar no campo antes de bipar. Espaços são removidos e letras são convertidas para maiúsculas automaticamente.

## Logs

| Onde | O quê |
| --- | --- |
| **Logs de acesso** (interface) | Logins, saídas, expirações e falhas, com IP e navegador |
| **Logs do sistema** (interface) | Toda ação dos usuários, com antes e depois |
| `C:\xampp\apache\logs\error.log` | Erros técnicos do PHP (exceções, falhas de banco, falha do `mysqldump`) |

## Solução de problemas

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| "Não foi possível acessar o banco de dados..." | MySQL desligado, banco não criado ou credenciais erradas | Ligue o MySQL no XAMPP, confira `config/database.php` e rode `setup.php` |
| "Ocorreu um erro inesperado" | Exceção não tratada | Veja o detalhe no `error.log` do Apache |
| Página sem estilo | `base_url` incorreto ou CSS não compilado | Ajuste `base_url` em `config/app.php`. Se alterou classes, rode `npm run build:css` |
| Login volta sempre para a tela de entrada | Cookie de sessão bloqueado pelo `path` | O endereço acessado precisa começar pelo `base_url` configurado |
| "Muitas tentativas erradas" | Bloqueio de força bruta | Aguarde o tempo indicado (15 minutos por padrão). As tentativas aparecem em **Logs de acesso** |
| "Sua sessão foi encerrada" | Senha alterada ou usuário desativado | Entre novamente com a senha atual |
| "Sessão expirada. Volte e tente novamente." | Token CSRF inválido (formulário aberto antes de a sessão expirar) | Recarregue a página e reenvie |
| Backup falha | MySQL desligado ou caminho do `mysqldump` incorreto | Confira `mysqldump` em `config/app.php`. O detalhe fica no `error.log` |
| Código de barras não aparece | `public/assets/vendor/JsBarcode.all.min.js` ausente | Rode `npm install && npm run vendor` |
| Etiqueta cortada ou com margem | Tamanho do papel diferente no driver | Use o mesmo tamanho nas Configurações e no driver, margens "Nenhuma" e escala 100% |
| `config/app.php` abre no navegador | `.htaccess` ignorado | Habilite `AllowOverride All` no `httpd.conf` e reinicie o Apache |
