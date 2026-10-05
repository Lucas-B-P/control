# Segurança

Este documento lista os controles de segurança implementados, as limitações conhecidas e as recomendações para colocar o sistema em produção.

## Controles implementados

### Autenticação

| Controle | Implementação |
| --- | --- |
| Armazenamento de senha | `password_hash` / `password_verify` (bcrypt, custo padrão do PHP) |
| Política de senha | Mínimo de 8 caracteres, com letras e números, sem conter o login (`senha_erro`) |
| Força bruta | 5 falhas no mesmo login ou 20 no mesmo IP bloqueiam por 15 minutos. Tentativas durante o bloqueio não renovam o prazo (`login_bloqueado`) |
| Enumeração de usuários | Mensagem única "Login ou senha incorretos." e `password_verify` executado mesmo para login inexistente, para que o tempo de resposta não revele se a conta existe |
| Fixação de sessão | `session_regenerate_id(true)` no login, no logout e na expiração. `use_strict_mode` habilitado |
| Revogação de sessões | Trocar a senha incrementa `usuarios.sessao_versao`, o que encerra as sessões abertas em outros computadores. Desativar o usuário encerra todas |
| Inatividade | A sessão expira após 120 minutos sem uso (configurável) |

### Sessão e cookies

- Cookie `control_sessao` com `HttpOnly`, `SameSite=Lax` e `Secure` automático sob HTTPS.
- O `path` fica restrito ao `base_url`, então o cookie não é enviado a outras aplicações no mesmo host, como o phpMyAdmin do XAMPP.
- `use_only_cookies`: o identificador de sessão nunca trafega pela URL.

### Autorização

- Toda página declara o requisito no topo: `exigir_login()`, `exigir_setor('hardware' | 'erp')` ou `exigir_admin()`.
- As regras do fluxo (quem pode mudar qual status) são verificadas no servidor, independentemente do que a interface exibe. Veja [FLUXO_DA_OS.md](FLUXO_DA_OS.md).
- Proteções de integridade: sempre há um administrador ativo, ninguém desativa ou exclui o próprio usuário, e usuários com histórico não podem ser excluídos.

### Entrada e saída

| Ameaça | Mitigação |
| --- | --- |
| SQL injection | 100% das consultas com prepared statements **nativos** (`EMULATE_PREPARES = false`). Nomes de colunas dinâmicos vêm apenas de listas fixas no código |
| XSS | Toda saída passa por `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8). O texto formatado das atualizações é escapado antes de receber marcação |
| CSRF | Token de 256 bits por sessão em todo formulário POST, comparado com `hash_equals` |
| Open redirect | O retorno após editar uma observação só aceita caminhos internos simples |
| Path traversal | O download de backup só serve arquivos que constam na listagem da pasta de backups |
| Execução de comando | O `mysqldump` recebe argumentos escapados e as credenciais vão num arquivo temporário (`--defaults-extra-file`), nunca na linha de comando |

### Cabeçalhos HTTP

| Cabeçalho | Valor |
| --- | --- |
| `Content-Security-Policy` | `default-src 'self'`, scripts e estilos locais, fontes do Google Fonts, `object-src 'none'`, `frame-ancestors 'none'`, `form-action 'self'` |
| `X-Frame-Options` | `DENY` (clickjacking) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `same-origin` |
| `Permissions-Policy` | Câmera, microfone e geolocalização desabilitados |
| `X-Powered-By` | Removido |

### Exposição de arquivos

- Só `public/` contém código executável pela web. `src/`, `templates/`, `config/`, `database/`, `docs/` e `design-system/` respondem 403.
- O `.htaccess` da raiz bloqueia `.md`, `.json`, `.lock`, `.log`, `.sql`, `.ini`, `.bak` e arquivos ocultos, e desliga a listagem de diretórios.
- A pasta `Backup/` não é acessível pelo navegador. O download passa pela página autenticada de administrador.
- `display_errors` fica desligado: erros vão para o log do Apache e o usuário vê uma mensagem genérica.
- `database/setup.php` recusa execução fora da linha de comando.

### Auditoria

- **Logs de acesso:** todo login, logout, expiração e falha, com motivo, IP e navegador.
- **Logs do sistema:** toda ação que altera dados, com usuário, setor, IP e os valores antes e depois. Senhas nunca são gravadas: o log registra apenas "alterada".

## Limitações conhecidas

| Item | Situação | Impacto |
| --- | --- | --- |
| CSP com `'unsafe-inline'` para scripts | Necessário pelos scripts inline de interação (modais, impressão) | Reduz a proteção em profundidade contra XSS. A mitigação principal (escape de saída) continua valendo |
| IP atrás de proxy reverso | O sistema usa `REMOTE_ADDR` | Atrás de um proxy, todos os acessos aparecem com o IP do proxy e o bloqueio por IP passa a valer para todos. Ajuste antes de publicar atrás de proxy |
| Sem segundo fator (2FA) | Não implementado | Aceitável para uso em rede interna |

## Checklist de implantação

Antes de colocar em produção:

- [ ] Trocar a senha do usuário `admin` no primeiro acesso.
- [ ] Criar um **usuário MySQL dedicado**, com senha e permissões apenas sobre o banco `control`, em vez de `root` sem senha.
- [ ] Manter `config/database.php` fora do controle de versão (já está no `.gitignore`).
- [ ] Habilitar **HTTPS**. O cookie passa a ser `Secure` automaticamente.
- [ ] Garantir `AllowOverride All` no Apache, para que os `.htaccess` sejam aplicados. Teste: `http://servidor/Control/config/app.php` deve responder **403**.
- [ ] Restringir o acesso ao servidor à rede interna (firewall).
- [ ] Bloquear o phpMyAdmin e o painel do XAMPP para acesso externo.
- [ ] Copiar os backups periodicamente para outra máquina ou para a rede.
- [ ] Manter PHP, Apache e MySQL atualizados.

## Relatando vulnerabilidades

Não abra issue pública para falhas de segurança. Use a opção **Report a vulnerability**, na aba **Security** do repositório no GitHub, descrevendo o problema, os passos para reproduzir e o impacto observado.
