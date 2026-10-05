# Control: Rastreio de Manutenção de Equipamentos

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-InnoDB-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-v4-06B6D4?logo=tailwindcss&logoColor=white)
![Dependências de runtime](https://img.shields.io/badge/depend%C3%AAncias%20de%20runtime-0-success)
![Licença MIT](https://img.shields.io/badge/licen%C3%A7a-MIT-blue)

Sistema web interno para controlar o ciclo de manutenção de equipamentos de TI. O setor de **Automação Hardware** abre a ordem de serviço (OS), registra diagnóstico e peças e imprime uma etiqueta com código de barras. Depois envia o aparelho à ADM. O setor de **Automação ERP** bipa a etiqueta para confirmar a chegada ou devolver o aparelho. Cada passo fica registrado com usuário, setor e horário.

O projeto usa **PHP puro, sem framework e sem dependências de runtime**. Ele roda em qualquer instalação do XAMPP. O Node.js só é necessário para recompilar o CSS.

---

## Sumário

- [Funcionalidades](#funcionalidades)
- [Stack](#stack)
- [Início rápido](#início-rápido)
- [Configuração](#configuração)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Documentação](#documentação)
- [Scripts](#scripts)
- [Licença](#licença)

## Funcionalidades

| Área | Recursos |
| --- | --- |
| **Ordens de serviço** | Abertura por patrimônio ou número de série, status configuráveis, diagnóstico, peças trocadas, observações editáveis e trajetória completa |
| **Fluxo entre setores** | O Hardware envia à ADM e o ERP confirma a chegada ou devolve com motivo. As regras ficam no servidor e há controle de concorrência |
| **Etiquetas** | Code128 gerado no navegador, sem internet, com tamanho configurável para impressoras térmicas |
| **Dashboard** | Indicadores por status e três gráficos em SVG: situação atual, abertas x encerradas e tempo em aberto |
| **Administração** | Usuários e setores, status, configurações, zona do administrador (desfazer, forçar status, excluir OS) |
| **Auditoria** | Log de acesso (logins, saídas e falhas) e log do sistema com valores antes e depois, ambos com filtros e paginação |
| **Comunicação** | Anúncio de atualizações, com aviso único após o login e popup por 24 horas |
| **Operação** | Backup do banco com um clique, guardando os 7 mais recentes, e download pela interface |
| **Segurança** | CSRF, bloqueio de força bruta, sessões invalidadas na troca de senha, CSP e cookies restritos. Veja [SEGURANCA.md](docs/SEGURANCA.md) |

## Stack

- **Back-end:** PHP 8.1+ com `strict_types`, PDO com prepared statements nativos e sessões nativas.
- **Banco:** MySQL 5.7+ ou MariaDB 10.4+ (InnoDB, `utf8mb4`).
- **Front-end:** HTML renderizado no servidor, Tailwind CSS v4 e JavaScript mínimo, sem frameworks.
- **Bibliotecas:** [JsBarcode](https://github.com/lindell/JsBarcode), servida localmente, e ícones [Lucide](https://lucide.dev) embutidos como SVG.
- **Servidor:** Apache 2.4 com `.htaccess` habilitado (`AllowOverride All`). O ambiente de referência é o XAMPP no Windows.

## Início rápido

**Pré-requisitos:** XAMPP com PHP 8.1 ou superior. O Node.js 18+ é opcional e só serve para recompilar o CSS.

```powershell
# 1. Clone dentro do htdocs do XAMPP
cd C:\xampp\htdocs
git clone https://github.com/Lucas-B-P/control.git Control
cd Control

# 2. Configure a conexão com o banco
copy config\database.example.php config\database.php

# 3. Com Apache e MySQL ligados no painel do XAMPP, crie o banco, as tabelas e o admin
C:\xampp\php\php.exe database\setup.php "SenhaForte123"
```

Acesse `http://localhost/Control/` e entre com o login **`admin`** e a senha informada. Sem argumento, a senha padrão é `admin123`; troque-a no primeiro acesso.

O `setup.php` é **idempotente**. Pode ser executado de novo a qualquer momento: ele aplica só as migrações pendentes e nunca recria nem apaga dados.

## Configuração

| Arquivo | Conteúdo | Versionado |
| --- | --- | --- |
| `config/database.php` | Host, porta, banco, usuário e senha do MySQL | **Não** (criado a partir do `.example`) |
| `config/app.php` | Nome, `base_url`, fuso horário, tempo de sessão, limites de login e caminho do `mysqldump` | Sim |
| Tela **Configurações** | Tamanho e título da etiqueta, gravados na tabela `configuracoes` | Banco |

Se o sistema for publicado em outra pasta ou domínio, ajuste `base_url` em `config/app.php`. O valor é usado nos links e no caminho do cookie de sessão.

## Estrutura do projeto

```text
Control/
├── config/             Configuração (app.php versionado, database.php local)
├── database/
│   ├── schema.sql      Estrutura completa e dados iniciais
│   └── setup.php       Instalação e migrações idempotentes (somente CLI)
├── docs/               Documentação técnica
├── public/             Único diretório servido pela web (front controllers por página)
│   ├── assets/         CSS compilado, imagens e JsBarcode
│   ├── equipamentos/
│   └── manutencoes/
├── src/                Regras de negócio e infraestrutura (bloqueado para a web)
│   ├── bootstrap.php   Ponto de entrada comum: configuração, cabeçalhos, sessão
│   ├── db.php          Conexão PDO e helpers de consulta/transação
│   ├── listagem.php    Filtros, paginação e consulta paginada
│   └── *.php           Um módulo por domínio (manutencoes, status, usuarios...)
├── templates/          Layout, componentes de interface e formulários
├── design-system/      Paleta, tipografia e padrões visuais
└── Backup/             Backups gerados pelo sistema (fora do Git)
```

Os detalhes de cada camada e as convenções estão em [docs/ARQUITETURA.md](docs/ARQUITETURA.md).

## Documentação

| Documento | Assunto |
| --- | --- |
| [Arquitetura](docs/ARQUITETURA.md) | Camadas, ciclo de uma requisição, módulos e convenções de código |
| [Fluxo da OS](docs/FLUXO_DA_OS.md) | Setores, permissões, status e regras de transição |
| [Banco de dados](docs/BANCO_DE_DADOS.md) | Modelo de dados, tabelas e migrações |
| [Segurança](docs/SEGURANCA.md) | Controles implementados e recomendações de implantação |
| [Operação](docs/OPERACAO.md) | Backup e restauração, atualização, etiquetas, leitor e solução de problemas |
| [Contribuindo](CONTRIBUTING.md) | Ambiente de desenvolvimento, padrões e checklist de entrega |

## Scripts

O arquivo `public/assets/app.css` já vem compilado. Só é preciso recompilar ao alterar classes nas telas ou em `src/input.css`.

```bash
npm install
npm run build       # copia o JsBarcode para public/assets/vendor e compila o CSS
npm run build:css   # apenas o CSS (minificado)
npm run watch:css   # recompila a cada alteração durante o desenvolvimento
```

## Licença

Distribuído sob a licença [MIT](LICENSE).
