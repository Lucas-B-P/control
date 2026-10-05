<?php

declare(strict_types=1);

function app_config(string $chave): mixed
{
    static $config = null;
    $config ??= require __DIR__ . '/../config/app.php';

    return $config[$chave] ?? null;
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $caminho = ''): string
{
    return rtrim(app_config('base_url'), '/') . '/' . ltrim($caminho, '/');
}

function asset(string $caminho): string
{
    $arquivo = __DIR__ . '/../public/assets/' . $caminho;
    $versao = is_file($arquivo) ? filemtime($arquivo) : 0;

    return url('assets/' . $caminho) . '?v=' . $versao;
}

function redirect(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function input(string $chave, string $padrao = ''): string
{
    $valor = $_POST[$chave] ?? $_GET[$chave] ?? $padrao;

    return is_string($valor) ? trim($valor) : $padrao;
}

function input_int(string $chave): int
{
    return (int) input($chave, '0');
}

/** Data no formato AAAA-MM-DD, ou '' quando ausente ou inválida. */
function input_data(string $chave): string
{
    $valor = input($chave);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
}

function ip_cliente(string $padrao = ''): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? $padrao);
}

function ip_legivel(string $ip): string
{
    return in_array($ip, ['::1', '127.0.0.1'], true) ? 'Este servidor' : $ip;
}

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function flash_pegar(): array
{
    $mensagens = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $mensagens;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        // 403, não 419: o Apache troca códigos que não conhece por 500.
        http_response_code(403);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function normalizar_codigo(string $codigo): string
{
    return mb_strtoupper(preg_replace('/\s+/', '', $codigo) ?? '');
}

function data_hora(?string $valor, bool $comSegundos = false): string
{
    return $valor ? date($comSegundos ? 'd/m/Y H:i:s' : 'd/m/Y H:i', strtotime($valor)) : '-';
}

function data_curta(?string $valor): string
{
    return $valor ? date('d/m/Y', strtotime($valor)) : '-';
}

function nao_encontrado(string $mensagem = 'Registro não encontrado.'): never
{
    http_response_code(404);
    render_header('Não encontrado', '');
    echo '<div class="card p-6"><p>' . e($mensagem) . '</p><a class="link mt-4 inline-block" href="' . e(url(pagina_inicial())) . '">Voltar ao início</a></div>';
    render_footer();
    exit;
}
