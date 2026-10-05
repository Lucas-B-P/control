<?php

return [
    'nome'     => 'Control Hardware',
    // Caminho da pasta public/ a partir da raiz do servidor web.
    'base_url' => '/Control/public',
    'timezone' => 'America/Manaus',
    // Tempo sem uso até a sessão expirar e pedir login de novo.
    'sessao_inatividade_min' => 120,
    // Bloqueio de login após tentativas erradas seguidas (por login e por computador).
    'login_max_tentativas'   => 5,
    'login_bloqueio_min'     => 15,
    // Usado pela página Backup para exportar o banco.
    'mysqldump' => 'C:/xampp/mysql/bin/mysqldump.exe',
];
