<?php

declare(strict_types=1);

/** Tom 500 do Tailwind de cada cor de status, para os gráficos em SVG. */
const CORES_GRAFICO = [
    'slate' => '#64748b', 'sky' => '#0ea5e9', 'blue' => '#3b82f6', 'indigo' => '#6366f1',
    'violet' => '#8b5cf6', 'pink' => '#ec4899', 'red' => '#ef4444', 'orange' => '#f97316',
    'amber' => '#f59e0b', 'emerald' => '#10b981', 'teal' => '#14b8a6',
];

/** OS por status, sem as que já chegaram na ADM (ciclo encerrado). */
function grafico_dados_situacao(array $contagens): array
{
    $fatias = [];
    foreach (status_todos() as $s) {
        $qtd = (int) ($contagens[$s['id']] ?? 0);
        if ($qtd > 0 && $s['sistema'] !== STATUS_RECEBIDO_ERP) {
            $fatias[] = ['id' => (int) $s['id'], 'nome' => $s['nome'], 'qtd' => $qtd, 'cor' => CORES_GRAFICO[$s['cor']] ?? CORES_GRAFICO['slate']];
        }
    }

    return $fatias;
}

/** Abertas e encerradas por dia, nos últimos $dias dias (incluindo hoje). */
function grafico_dados_evolucao(int $dias = 14): array
{
    $inicio = (new DateTimeImmutable('today'))->modify('-' . ($dias - 1) . ' days');
    $contar = fn (string $coluna): array => db_pares(
        "SELECT DATE({$coluna}) AS dia, COUNT(*) FROM manutencoes WHERE {$coluna} >= ? GROUP BY dia",
        [$inicio->format('Y-m-d')]
    );
    $abertas = $contar('aberta_em');
    $encerradas = $contar('concluida_em');

    $serie = [];
    for ($i = 0; $i < $dias; $i++) {
        $dia = $inicio->modify("+{$i} days")->format('Y-m-d');
        $serie[] = ['dia' => $dia, 'abertas' => (int) ($abertas[$dia] ?? 0), 'encerradas' => (int) ($encerradas[$dia] ?? 0)];
    }

    return $serie;
}

/** OS em andamento agrupadas por quantos dias estão abertas, e as mais antigas. */
function grafico_dados_tempo(): array
{
    $faixas = [
        ['Hoje', 0, 0, '#10b981'],
        ['1 a 3 dias', 1, 3, '#84cc16'],
        ['4 a 7 dias', 4, 7, '#f59e0b'],
        ['8 a 15 dias', 8, 15, '#f97316'],
        ['Mais de 15 dias', 16, PHP_INT_MAX, '#ef4444'],
    ];
    $abertos = status_ids_abertos() ?: [0];
    $os = db_lista(
        'SELECT m.id, m.aberta_em, m.status_id, e.codigo, e.tipo, e.marca, e.modelo
         FROM manutencoes m JOIN equipamentos e ON e.id = m.equipamento_id
         WHERE m.status_id IN (' . marcadores($abertos) . ') ORDER BY m.aberta_em',
        $abertos
    );

    $hoje = new DateTimeImmutable('today');
    $contagem = array_fill(0, count($faixas), 0);
    foreach ($os as &$item) {
        $item['dias'] = (int) (new DateTimeImmutable(substr($item['aberta_em'], 0, 10)))->diff($hoje)->days;
        foreach ($faixas as $i => [, $min, $max]) {
            if ($item['dias'] >= $min && $item['dias'] <= $max) {
                $contagem[$i]++;
                break;
            }
        }
    }
    unset($item);

    return [
        'faixas' => array_map(fn ($f, $qtd) => ['nome' => $f[0], 'qtd' => $qtd, 'cor' => $f[3]], $faixas, $contagem),
        'antigas' => array_slice($os, 0, 5),
        'total' => count($os),
    ];
}

function grafico_rosca(array $fatias): string
{
    $total = array_sum(array_column($fatias, 'qtd'));
    if ($total === 0) {
        return '<p class="py-10 text-center text-sm text-muted-foreground">Nenhuma OS no fluxo no momento.</p>';
    }

    // Circunferência 100 (r = 15.915): cada fatia usa a própria porcentagem como comprimento do traço.
    $svg = '<svg viewBox="0 0 42 42" class="mx-auto size-56 shrink-0" role="img" aria-label="Distribuição das ' . $total . ' OS por status">';
    $svg .= '<circle cx="21" cy="21" r="15.915" fill="none" stroke="#f1f5f9" stroke-width="6"/>';
    $acumulado = 0.0;
    foreach ($fatias as $f) {
        $pct = $f['qtd'] / $total * 100;
        $svg .= sprintf(
            '<circle cx="21" cy="21" r="15.915" fill="none" stroke="%s" stroke-width="6" stroke-dasharray="%.3f %.3f" stroke-dashoffset="%.3f"><title>%s: %d OS (%s%%)</title></circle>',
            $f['cor'], $pct, 100 - $pct, 25 - $acumulado, e($f['nome']), $f['qtd'], number_format($pct, 0)
        );
        $acumulado += $pct;
    }
    $svg .= '<text x="21" y="21" text-anchor="middle" font-size="7" font-weight="700" fill="#0f172a">' . $total . '</text>';
    $svg .= '<text x="21" y="26.5" text-anchor="middle" font-size="2.6" fill="#64748b">OS no fluxo</text></svg>';

    $legenda = '<ul class="grid w-full gap-1.5 text-sm">';
    foreach ($fatias as $f) {
        $legenda .= '<li><a class="flex items-center gap-2 rounded px-2 py-1 hover:bg-slate-50" href="' . e(url('manutencoes/index.php?status=' . $f['id'])) . '">'
            . '<span class="size-3 shrink-0 rounded-sm" style="background:' . $f['cor'] . '" aria-hidden="true"></span>'
            . '<span class="truncate">' . e($f['nome']) . '</span>'
            . '<span class="ml-auto font-semibold tabular-nums">' . $f['qtd'] . '</span>'
            . '<span class="w-10 text-right text-xs text-muted-foreground tabular-nums">' . number_format($f['qtd'] / $total * 100, 0) . '%</span></a></li>';
    }

    return '<div class="flex flex-col items-center gap-6 md:flex-row">' . $svg . $legenda . '</ul></div>';
}

function grafico_linhas(array $serie): string
{
    $largura = 640; $altura = 240; $esq = 32; $dir = 12; $topo = 12; $base = 28;
    $max = max(1, ...array_column($serie, 'abertas'), ...array_column($serie, 'encerradas'));
    $max = (int) (ceil($max / 4) * 4);
    $n = count($serie);
    $x = fn (int $i) => $esq + ($n > 1 ? $i * ($largura - $esq - $dir) / ($n - 1) : 0);
    $y = fn (int $v) => $topo + ($altura - $topo - $base) * (1 - $v / $max);

    $svg = '<svg viewBox="0 0 ' . $largura . ' ' . $altura . '" class="w-full" role="img" aria-label="OS abertas e encerradas por dia nos últimos ' . $n . ' dias">';
    for ($g = 0; $g <= 4; $g++) {
        $valor = $max * $g / 4;
        $gy = $y((int) $valor);
        $svg .= sprintf('<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" stroke="#e2e8f0"/>', $esq, $largura - $dir, $gy, $gy);
        $svg .= sprintf('<text x="%d" y="%.1f" text-anchor="end" font-size="11" fill="#64748b">%d</text>', $esq - 6, $gy + 4, $valor);
    }
    foreach ($serie as $i => $p) {
        if ($i % 2 === ($n - 1) % 2) {
            $svg .= sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="11" fill="#64748b">%s</text>', $x($i), $altura - 8, date('d/m', strtotime($p['dia'])));
        }
    }

    $linhas = ['abertas' => ['#0072C6', 'Abertas'], 'encerradas' => ['#10b981', 'Encerradas']];
    foreach ($linhas as $chave => [$cor, $rotulo]) {
        $pontos = implode(' ', array_map(fn ($p, $i) => sprintf('%.1f,%.1f', $x($i), $y($p[$chave])), $serie, array_keys($serie)));
        $svg .= '<polyline points="' . $pontos . '" fill="none" stroke="' . $cor . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
        foreach ($serie as $i => $p) {
            $svg .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="4" fill="#fff" stroke="%s" stroke-width="2"><title>%s: %d %s</title></circle>',
                $x($i), $y($p[$chave]), $cor, date('d/m', strtotime($p['dia'])), $p[$chave], mb_strtolower($rotulo)
            );
        }
    }
    $svg .= '</svg>';

    $totAbertas = array_sum(array_column($serie, 'abertas'));
    $totEncerradas = array_sum(array_column($serie, 'encerradas'));
    $saldo = $totAbertas - $totEncerradas;
    $textoSaldo = match (true) {
        $saldo > 0 => 'Entraram <strong class="text-amber-700">' . $saldo . ' a mais</strong> do que saíram no período.',
        $saldo < 0 => 'Saíram <strong class="text-emerald-700">' . -$saldo . ' a mais</strong> do que entraram no período.',
        default => 'Entradas e saídas empatadas no período.',
    };
    $resumo = '<div class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">'
        . '<span class="flex items-center gap-2"><span class="h-0.5 w-5 rounded bg-primary" aria-hidden="true"></span>Abertas: <strong class="tabular-nums">' . $totAbertas . '</strong></span>'
        . '<span class="flex items-center gap-2"><span class="h-0.5 w-5 rounded bg-emerald-500" aria-hidden="true"></span>Encerradas: <strong class="tabular-nums">' . $totEncerradas . '</strong></span>'
        . '<span class="text-muted-foreground">' . $textoSaldo . '</span></div>';

    return $resumo . $svg;
}

function grafico_tempo(array $dados): string
{
    if ($dados['total'] === 0) {
        return '<p class="py-10 text-center text-sm text-muted-foreground">Nenhuma OS em andamento no momento.</p>';
    }
    $max = max(1, ...array_column($dados['faixas'], 'qtd'));

    $html = '<div class="grid gap-6 lg:grid-cols-5"><ul class="space-y-3 lg:col-span-3" aria-label="OS em andamento por tempo em aberto">';
    foreach ($dados['faixas'] as $f) {
        $html .= '<li class="grid grid-cols-[7.5rem_1fr_2rem] items-center gap-3 text-sm">'
            . '<span class="text-muted-foreground">' . e($f['nome']) . '</span>'
            . '<span class="h-6 overflow-hidden rounded bg-slate-100"><span class="block h-full rounded" style="width:' . round($f['qtd'] / $max * 100, 1) . '%;background:' . $f['cor'] . '"></span></span>'
            . '<span class="text-right font-semibold tabular-nums">' . $f['qtd'] . '</span></li>';
    }
    $html .= '</ul><div class="lg:col-span-2"><h3 class="mb-2 text-sm font-semibold">Mais antigas em andamento</h3><ul class="divide-y divide-border rounded-md border border-border text-sm">';
    foreach ($dados['antigas'] as $os) {
        $html .= '<li><a class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" href="' . e(url('manutencoes/ver.php?id=' . $os['id'])) . '">'
            . '<span class="font-mono text-primary">#' . (int) $os['id'] . '</span>'
            . '<span class="min-w-0 flex-1 truncate">' . e($os['codigo']) . ' · ' . e(equipamento_descricao($os)) . '</span>'
            . '<span class="shrink-0 text-xs font-semibold ' . ($os['dias'] > 7 ? 'text-red-700' : 'text-muted-foreground') . '">'
            . ($os['dias'] === 0 ? 'hoje' : $os['dias'] . ' dia' . ($os['dias'] > 1 ? 's' : '')) . '</span></a></li>';
    }

    return $html . '</ul></div></div>';
}
