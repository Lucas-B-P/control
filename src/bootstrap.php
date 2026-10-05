<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

date_default_timezone_set(app_config('timezone'));

// Erros vão para o log do Apache, nunca para a tela (evita vazar caminhos e estrutura).
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

require __DIR__ . '/db.php';
require __DIR__ . '/listagem.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/usuarios.php';
require __DIR__ . '/auditoria.php';
require __DIR__ . '/backup.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/configuracoes.php';
require __DIR__ . '/status.php';
require __DIR__ . '/equipamentos.php';
require __DIR__ . '/manutencoes.php';
require __DIR__ . '/graficos.php';
require __DIR__ . '/atualizacoes.php';
require __DIR__ . '/../templates/layout.php';
require __DIR__ . '/../templates/componentes.php';

set_exception_handler(function (Throwable $t): void {
    error_log((string) $t);
    http_response_code(500);
    $mensagem = $t instanceof PDOException
        ? 'Não foi possível acessar o banco de dados. Verifique se o MySQL do XAMPP está ligado e se o banco foi criado (php database/setup.php).'
        : 'Ocorreu um erro inesperado. Tente novamente.';
    echo '<!DOCTYPE html><meta charset="UTF-8"><title>Erro</title><p style="font-family:sans-serif;padding:2rem">' . e($mensagem) . '</p>';
});

// Cookie restrito à pasta do sistema: não é enviado ao phpMyAdmin/dashboard do XAMPP no mesmo endereço.
session_name('control_sessao');
session_start([
    'cookie_path'     => rtrim(app_config('base_url'), '/') . '/',
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'gc_maxlifetime'  => (int) app_config('sessao_inatividade_min') * 60,
]);

sessao_verificar_inatividade();
