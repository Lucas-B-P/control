<?php

declare(strict_types=1);

const CAMPOS_USUARIO = [
    'nome'  => 'Nome',
    'login' => 'Login',
    'setor' => 'Setor',
    'admin' => 'Administrador',
    'ativo' => 'Ativo',
];

function usuario_por_id(int $id): ?array
{
    return db_linha('SELECT id, nome, login, setor, admin, ativo FROM usuarios WHERE id = ?', [$id]);
}

/** Usuários com a quantidade de registros no histórico e a data do último login. */
function usuarios_listagem(): array
{
    return db_lista(
        "SELECT u.id, u.nome, u.login, u.setor, u.admin, u.ativo,
                (SELECT COUNT(*) FROM movimentacoes mv WHERE mv.usuario_id = u.id)
              + (SELECT COUNT(*) FROM manutencoes m WHERE m.tecnico_id = u.id) AS registros,
                (SELECT MAX(l.criado_em) FROM logs_acesso l WHERE l.usuario_id = u.id AND l.evento = 'login') AS ultimo_acesso
         FROM usuarios u ORDER BY u.ativo DESC, u.setor, u.nome"
    );
}

function usuarios_para_filtro(): array
{
    return db_lista('SELECT id, nome, login FROM usuarios ORDER BY nome');
}

/** Técnicos que podem ser responsáveis por uma OS: usuários ativos do Hardware. */
function tecnicos_ativos(): array
{
    return db_lista("SELECT id, nome FROM usuarios WHERE ativo = 1 AND setor = 'hardware' ORDER BY nome");
}

function tecnico_valido(int $id): bool
{
    return in_array($id, array_map('intval', array_column(tecnicos_ativos(), 'id')), true);
}

/** Retorna a mensagem de erro do login ou null se ele for válido e estiver livre. */
function login_erro(string $login, int $ignorarId = 0): ?string
{
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $login)) {
        return 'Use de 3 a 50 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado.';
    }
    $emUso = db_valor('SELECT 1 FROM usuarios WHERE login = ? AND id <> ?', [$login, $ignorarId]);

    return $emUso ? 'Este login já está em uso.' : null;
}

function admins_ativos_exceto(int $id): int
{
    return (int) db_valor('SELECT COUNT(*) FROM usuarios WHERE admin = 1 AND ativo = 1 AND id <> ?', [$id]);
}

/** Quantas vezes o usuário aparece no histórico (movimentações ou como técnico de OS). */
function usuario_registros(int $id): int
{
    return (int) db_valor(
        'SELECT (SELECT COUNT(*) FROM movimentacoes WHERE usuario_id = ?)
              + (SELECT COUNT(*) FROM manutencoes WHERE tecnico_id = ?)',
        [$id, $id]
    );
}

function usuario_dados_formulario(): array
{
    return [
        'nome'  => mb_substr(input('nome'), 0, 100),
        'login' => mb_strtolower(input('login')),
        'setor' => input('setor'),
        'admin' => empty($_POST['admin']) ? 0 : 1,
    ];
}

/**
 * Erros por campo. Na edição ($id > 0) a senha é opcional e o último administrador ativo é preservado.
 *
 * @return array<string, string>
 */
function usuario_validar(array $dados, string $senha, int $id = 0): array
{
    $erros = [];
    if ($dados['nome'] === '') {
        $erros['nome'] = 'Informe o nome.';
    }
    if ($erro = login_erro($dados['login'], $id)) {
        $erros['login'] = $erro;
    }
    if (!array_key_exists($dados['setor'], SETORES)) {
        $erros['setor'] = 'Escolha o setor.';
    }
    $senhaObrigatoria = $id === 0;
    if (($senhaObrigatoria || $senha !== '') && ($erro = senha_erro($senha, $dados['login']))) {
        $erros['senha'] = $erro;
    }
    $perdeAdmin = !$dados['admin'] || !($dados['ativo'] ?? 1);
    if ($id > 0 && $perdeAdmin && admins_ativos_exceto($id) === 0) {
        $erros['admin'] = 'É preciso manter pelo menos um administrador ativo.';
    }

    return $erros;
}

function usuario_criar(array $dados, string $senha): void
{
    $id = db_inserir(
        'INSERT INTO usuarios (nome, login, senha_hash, setor, admin) VALUES (?, ?, ?, ?, ?)',
        [$dados['nome'], $dados['login'], password_hash($senha, PASSWORD_DEFAULT), $dados['setor'], $dados['admin']]
    );
    auditar(
        'usuario',
        'Usuário criado',
        'Usuário ' . $dados['nome'] . ' (' . $dados['login'] . ') criado no setor ' . SETORES[$dados['setor']] . ($dados['admin'] ? ', como administrador' : ''),
        $id
    );
}

function usuario_atualizar(array $antes, array $dados, string $senha): void
{
    $id = (int) $antes['id'];
    $sql = 'UPDATE usuarios SET nome = :nome, login = :login, setor = :setor, admin = :admin, ativo = :ativo';
    $params = $dados + ['id' => $id];
    if ($senha !== '') {
        $sql .= ', senha_hash = :senha';
        $params['senha'] = password_hash($senha, PASSWORD_DEFAULT);
    }
    db_executar($sql . ' WHERE id = :id', $params);

    $legivel = fn (array $u) => ['setor' => SETORES[$u['setor']] ?? $u['setor'], 'admin' => sim_nao($u['admin']), 'ativo' => sim_nao($u['ativo'])] + $u;
    $mudou = diferencas($legivel($antes), $legivel($dados), CAMPOS_USUARIO);
    if ($senha !== '') {
        $mudou['Senha'] = ['********', 'alterada'];
        sessoes_invalidar($id);
    }
    if ($mudou) {
        auditar('usuario', 'Usuário editado', 'Usuário ' . $dados['nome'] . ' (' . $dados['login'] . '): ' . implode(', ', array_keys($mudou)) . ' alterado(s)', $id, $mudou);
    }
}

function usuario_alternar(int $id, array $responsavel): void
{
    if ($id === (int) $responsavel['id']) {
        throw new RuntimeException('Você não pode desativar o próprio usuário.');
    }
    $alvo = usuario_por_id($id) ?? throw new RuntimeException('Usuário não encontrado.');
    db_executar('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$id]);
    $acao = $alvo['ativo'] ? 'desativado' : 'reativado';
    auditar('usuario', 'Usuário ' . $acao, 'Usuário ' . $alvo['nome'] . ' (' . $alvo['login'] . ') ' . $acao, $id);
}

/** @return string nome do usuário excluído */
function usuario_excluir(int $id, array $responsavel): string
{
    $alvo = usuario_por_id($id) ?? throw new RuntimeException('Usuário não encontrado.');
    if ($id === (int) $responsavel['id']) {
        throw new RuntimeException('Você não pode excluir o próprio usuário.');
    }
    if ($alvo['admin'] && $alvo['ativo'] && admins_ativos_exceto($id) === 0) {
        throw new RuntimeException('É preciso manter pelo menos um administrador ativo.');
    }
    if (usuario_registros($id) > 0) {
        throw new RuntimeException($alvo['nome'] . ' tem ações registradas em OS e faz parte do histórico. Desative o usuário em vez de excluir: ele deixa de entrar no sistema e não aparece mais como técnico.');
    }
    db_executar('DELETE FROM usuarios WHERE id = ?', [$id]);
    auditar('usuario', 'Usuário excluído', 'Usuário ' . $alvo['nome'] . ' (' . $alvo['login'] . ', ' . SETORES[$alvo['setor']] . ') excluído', $id);

    return $alvo['nome'];
}
