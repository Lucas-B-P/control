<?php

declare(strict_types=1);

function config_todas(): array
{
    static $cache = null;
    $cache ??= db_pares('SELECT chave, valor FROM configuracoes');

    return $cache;
}

function config_valor(string $chave, string $padrao = ''): string
{
    return config_todas()[$chave] ?? $padrao;
}

const CONFIG_ETIQUETA = [
    'etiqueta_largura_mm' => 'Largura da etiqueta (mm)',
    'etiqueta_altura_mm'  => 'Altura da etiqueta (mm)',
    'etiqueta_titulo'     => 'Título da etiqueta',
];

function config_etiqueta_formulario(): array
{
    return [
        'etiqueta_largura_mm' => str_replace(',', '.', input('etiqueta_largura_mm')),
        'etiqueta_altura_mm'  => str_replace(',', '.', input('etiqueta_altura_mm')),
        'etiqueta_titulo'     => mb_substr(input('etiqueta_titulo'), 0, 40),
    ];
}

/** @return array<string, string> erros por campo */
function config_etiqueta_validar(array $valores): array
{
    $erros = [];
    if (!numero_entre($valores['etiqueta_largura_mm'], 20, 120)) {
        $erros['etiqueta_largura_mm'] = 'Informe uma largura entre 20 e 120 mm.';
    }
    if (!numero_entre($valores['etiqueta_altura_mm'], 10, 100)) {
        $erros['etiqueta_altura_mm'] = 'Informe uma altura entre 10 e 100 mm.';
    }

    return $erros;
}

function numero_entre(string $valor, float $minimo, float $maximo): bool
{
    $numero = filter_var($valor, FILTER_VALIDATE_FLOAT);

    return $numero !== false && $numero >= $minimo && $numero <= $maximo;
}

function config_etiqueta_salvar(array $valores): void
{
    $mudou = diferencas(config_todas(), $valores, CONFIG_ETIQUETA);
    foreach ($valores as $chave => $valor) {
        config_salvar($chave, (string) $valor);
    }
    if ($mudou) {
        auditar('configuracao', 'Configurações alteradas', implode(', ', array_keys($mudou)) . ' alterado(s)', null, $mudou);
    }
}

function config_salvar(string $chave, string $valor): void
{
    db_executar('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$chave, $valor]);
}
