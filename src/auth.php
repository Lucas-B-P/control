<?php

declare(strict_types=1);

const SETORES = [
    'hardware' => 'Automação Hardware',
    'erp'      => 'Automação ERP',
];

function usuario_atual(): ?array
{
    static $usuario = false;

    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $usuario = db_linha('SELECT id, nome, login, setor, admin, sessao_versao FROM usuarios WHERE id = ? AND ativo = 1', [$_SESSION['usuario_id']]);
            // Troca de senha incrementa sessao_versao e derruba as sessões abertas em outros computadores.
            if ($usuario && (int) $usuario['sessao_versao'] !== (int) ($_SESSION['sessao_versao'] ?? -1)) {
                $usuario = null;
            }
            if (!$usuario) {
                $_SESSION = ['aviso_login' => 'Sua sessão foi encerrada (senha alterada ou acesso desativado). Entre novamente.'];
            }
        }
    }

    return $usuario;
}

function sessao_verificar_inatividade(): void
{
    if (empty($_SESSION['usuario_id'])) {
        return;
    }
    $limite = (int) app_config('sessao_inatividade_min') * 60;
    if (isset($_SESSION['ultimo_uso']) && time() - (int) $_SESSION['ultimo_uso'] > $limite) {
        $id = (int) $_SESSION['usuario_id'];
        acesso_registrar('logout', $id, (string) db_valor('SELECT login FROM usuarios WHERE id = ?', [$id]), 'Sessão expirada por inatividade');
        $_SESSION = ['aviso_login' => 'Sua sessão expirou por inatividade. Entre novamente.'];
        session_regenerate_id(true);

        return;
    }
    $_SESSION['ultimo_uso'] = time();
}

/** Retorna a mensagem de erro da senha nova ou null se ela for aceitável. */
function senha_erro(string $senha, string $login = ''): ?string
{
    if (mb_strlen($senha) < 8) {
        return 'A senha precisa ter pelo menos 8 caracteres.';
    }
    if (!preg_match('/\pL/u', $senha) || !preg_match('/\d/', $senha)) {
        return 'A senha precisa ter letras e números.';
    }
    if ($login !== '' && mb_stripos($senha, $login) !== false) {
        return 'A senha não pode conter o login.';
    }

    return null;
}

/** Invalida as sessões abertas do usuário (em outros computadores) após troca de senha. */
function sessoes_invalidar(int $usuarioId): void
{
    db_executar('UPDATE usuarios SET sessao_versao = sessao_versao + 1 WHERE id = ?', [$usuarioId]);
    if ((int) ($_SESSION['usuario_id'] ?? 0) === $usuarioId) {
        $_SESSION['sessao_versao'] = (int) db_valor('SELECT sessao_versao FROM usuarios WHERE id = ?', [$usuarioId]);
    }
}

function e_setor(string $setor): bool
{
    return (usuario_atual()['setor'] ?? null) === $setor;
}

function e_admin(): bool
{
    return (bool) (usuario_atual()['admin'] ?? false);
}

function exigir_login(): array
{
    $usuario = usuario_atual();
    if ($usuario === null) {
        redirect('login.php');
    }

    return $usuario;
}

/**
 * O administrador acessa qualquer setor; na página ele age como o setor dela,
 * para que as regras do fluxo e a trajetória usem o setor correto.
 */
function exigir_setor(string $setor): array
{
    $usuario = exigir_login();
    if ($usuario['setor'] !== $setor && !$usuario['admin']) {
        acesso_negado();
    }

    return ['setor' => $setor] + $usuario;
}

function exigir_admin(): array
{
    $usuario = exigir_login();
    if (!$usuario['admin']) {
        acesso_negado();
    }

    return $usuario;
}

function pagina_inicial(): string
{
    return e_setor('erp') ? 'consulta.php' : 'index.php';
}

function acesso_negado(): never
{
    http_response_code(403);
    render_header('Acesso restrito', '');
    echo '<div class="card p-6"><p>Esta função não está disponível para o seu setor ('
        . e(SETORES[usuario_atual()['setor']] ?? '') . ').</p>'
        . '<a class="link mt-4 inline-block" href="' . e(url(pagina_inicial())) . '">Voltar ao início</a></div>';
    render_footer();
    exit;
}

/** Retorna null quando entrou, ou a mensagem para mostrar na tela de login. */
function tentar_login(string $login, string $senha): ?string
{
    $login = mb_strtolower(mb_substr($login, 0, 50));
    $usuario = db_linha('SELECT id, senha_hash, ativo, sessao_versao FROM usuarios WHERE login = ?', [$login]);

    if ($minutos = login_bloqueado($login)) {
        acesso_registrar('falha', $usuario ? (int) $usuario['id'] : null, $login, 'Bloqueado: excesso de tentativas');

        return "Muitas tentativas erradas. Aguarde {$minutos} minuto(s) e tente novamente.";
    }

    // Sempre executa password_verify, para o tempo de resposta não revelar se o login existe.
    $hashFalso = '$2y$10$0m4j/lDSYQbQ1fW0Z7vj8.bKYkMCQ5TiF1mtt1W5q9tnsEFF4.xKe';
    $senhaOk = password_verify($senha, $usuario['senha_hash'] ?? $hashFalso);

    $falha = match (true) {
        !$usuario => 'Usuário inexistente',
        !$senhaOk => 'Senha incorreta',
        !$usuario['ativo'] => 'Usuário desativado',
        default => null,
    };
    if ($falha) {
        acesso_registrar('falha', $usuario ? (int) $usuario['id'] : null, $login, $falha);

        return 'Login ou senha incorretos.';
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['sessao_versao'] = (int) $usuario['sessao_versao'];
    $_SESSION['ultimo_uso'] = time();
    acesso_registrar('login', (int) $usuario['id'], $login);

    return null;
}

/**
 * Minutos restantes de bloqueio, ou 0. Conta as falhas recentes do login (desde o último acesso certo)
 * e do computador (IP), para barrar tanto ataque a uma conta quanto varredura de várias contas.
 */
function login_bloqueado(string $login): int
{
    $max = (int) app_config('login_max_tentativas');
    $janela = (int) app_config('login_bloqueio_min');
    // Tentativas feitas durante o bloqueio não contam, senão o bloqueio se renovaria indefinidamente.
    $falha = "f.evento = 'falha' AND f.detalhe <> 'Bloqueado: excesso de tentativas'";
    $ip = ip_cliente();
    $r = db_linha(
        "SELECT
            (SELECT COUNT(*) FROM logs_acesso f WHERE {$falha} AND f.login = ?
                AND f.criado_em > GREATEST(NOW() - INTERVAL {$janela} MINUTE,
                    COALESCE((SELECT MAX(s.criado_em) FROM logs_acesso s WHERE s.evento = 'login' AND s.login = ?), '2000-01-01'))) AS por_login,
            (SELECT COUNT(*) FROM logs_acesso f WHERE {$falha} AND f.ip = ?
                AND f.criado_em > NOW() - INTERVAL {$janela} MINUTE) AS por_ip,
            (SELECT MIN(f.criado_em) FROM logs_acesso f WHERE {$falha} AND (f.login = ? OR f.ip = ?)
                AND f.criado_em > NOW() - INTERVAL {$janela} MINUTE) AS primeira",
        [$login, $login, $ip, $login, $ip]
    );

    if ((int) $r['por_login'] < $max && (int) $r['por_ip'] < $max * 4) {
        return 0;
    }
    $restante = $janela * 60 - (time() - strtotime((string) $r['primeira']));

    return max(1, (int) ceil($restante / 60));
}

function sair(): void
{
    if ($usuario = usuario_atual()) {
        acesso_registrar('logout', (int) $usuario['id'], $usuario['login']);
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

function acesso_registrar(string $evento, ?int $usuarioId, string $login, ?string $detalhe = null): void
{
    db_executar(
        'INSERT INTO logs_acesso (usuario_id, login, evento, detalhe, ip, navegador) VALUES (?, ?, ?, ?, ?, ?)',
        [
            $usuarioId,
            mb_substr($login, 0, 50),
            $evento,
            $detalhe,
            ip_cliente('desconhecido'),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]
    );
}

/** Resume o user agent em "Navegador · Sistema". */
function navegador_resumo(?string $agente): string
{
    if (!$agente) {
        return '-';
    }
    $navegador = match (true) {
        str_contains($agente, 'Edg/') => 'Edge',
        str_contains($agente, 'OPR/') => 'Opera',
        str_contains($agente, 'Firefox/') => 'Firefox',
        str_contains($agente, 'Chrome/') => 'Chrome',
        str_contains($agente, 'Safari/') => 'Safari',
        default => 'Outro',
    };
    $sistema = match (true) {
        str_contains($agente, 'Windows') => 'Windows',
        str_contains($agente, 'Android') => 'Android',
        str_contains($agente, 'iPhone'), str_contains($agente, 'iPad') => 'iOS',
        str_contains($agente, 'Mac OS') => 'macOS',
        str_contains($agente, 'Linux') => 'Linux',
        default => '',
    };

    return $sistema ? "{$navegador} · {$sistema}" : $navegador;
}
