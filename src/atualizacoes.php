<?php

declare(strict_types=1);

const ATUALIZACAO_POPUP_HORAS = 24;

function atualizacoes_publicadas(): array
{
    return db_lista('SELECT * FROM atualizacoes WHERE publicado = 1 ORDER BY publicado_em DESC, id DESC');
}

function atualizacoes_todas(): array
{
    return db_lista(
        'SELECT a.*, u.nome AS criado_por_nome,
            (SELECT COUNT(*) FROM atualizacoes_vistas v WHERE v.atualizacao_id = a.id) AS vistas
         FROM atualizacoes a LEFT JOIN usuarios u ON u.id = a.criado_por
         ORDER BY a.publicado ASC, a.publicado_em DESC, a.id DESC'
    );
}

function atualizacao_por_id(int $id): ?array
{
    return db_linha('SELECT * FROM atualizacoes WHERE id = ?', [$id]);
}

/** Publicadas que o usuário ainda não viu, da mais recente para a mais antiga. */
function atualizacoes_nao_vistas(int $usuarioId): array
{
    return db_lista(
        'SELECT a.* FROM atualizacoes a
         WHERE a.publicado = 1
           AND NOT EXISTS (SELECT 1 FROM atualizacoes_vistas v WHERE v.atualizacao_id = a.id AND v.usuario_id = ?)
         ORDER BY a.publicado_em DESC, a.id DESC',
        [$usuarioId]
    );
}

function atualizacoes_marcar_vistas(int $usuarioId, array $ids): void
{
    $stmt = db()->prepare('INSERT IGNORE INTO atualizacoes_vistas (usuario_id, atualizacao_id) VALUES (?, ?)');
    foreach ($ids as $id) {
        $stmt->execute([$usuarioId, (int) $id]);
    }
}

/** A atualização mais recente publicada dentro da janela do popup. */
function atualizacao_em_destaque(): ?array
{
    return db_linha(
        'SELECT * FROM atualizacoes WHERE publicado = 1 AND publicado_em >= NOW() - INTERVAL ' . ATUALIZACAO_POPUP_HORAS . ' HOUR
         ORDER BY publicado_em DESC, id DESC LIMIT 1'
    );
}

function atualizacao_rotulo(array $atualizacao): string
{
    return '"' . $atualizacao['titulo'] . '"' . ($atualizacao['versao'] ? ' (versão ' . $atualizacao['versao'] . ')' : '');
}

function atualizacao_dados_formulario(int $id): array
{
    return [
        'id'        => $id,
        'titulo'    => mb_substr(input('titulo'), 0, 150),
        'versao'    => mb_substr(input('versao'), 0, 30),
        'resumo'    => mb_substr(input('resumo'), 0, 300),
        'conteudo'  => input('conteudo'),
        'publicado' => empty($_POST['publicado']) ? 0 : 1,
    ];
}

/** @return array<string, string> erros por campo */
function atualizacao_validar(array $dados): array
{
    $erros = [];
    if ($dados['titulo'] === '') {
        $erros['titulo'] = 'Informe o título.';
    }
    if ($dados['resumo'] === '') {
        $erros['resumo'] = 'Escreva um resumo curto (aparece no aviso e no popup).';
    }
    if ($dados['conteudo'] === '') {
        $erros['conteudo'] = 'Descreva a atualização em detalhes.';
    }

    return $erros;
}

function atualizacao_criar(array $dados, int $autorId): void
{
    $id = db_inserir(
        'INSERT INTO atualizacoes (titulo, versao, resumo, conteudo, publicado, publicado_em, criado_por)
         VALUES (?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL), ?)',
        [$dados['titulo'], $dados['versao'] ?: null, $dados['resumo'], $dados['conteudo'], $dados['publicado'], $dados['publicado'], $autorId]
    );
    $acao = $dados['publicado'] ? 'Atualização publicada' : 'Atualização criada (rascunho)';
    auditar('atualizacao', $acao, 'Anúncio ' . atualizacao_rotulo($dados), $id);
}

function atualizacao_editar(array $atual, array $dados): void
{
    $id = (int) $atual['id'];
    db_executar(
        'UPDATE atualizacoes SET titulo = ?, versao = ?, resumo = ?, conteudo = ?, publicado = ?,
            publicado_em = IF(? = 1 AND publicado_em IS NULL, NOW(), publicado_em) WHERE id = ?',
        [$dados['titulo'], $dados['versao'] ?: null, $dados['resumo'], $dados['conteudo'], $dados['publicado'], $dados['publicado'], $id]
    );

    $legivel = fn (array $a) => ['publicado' => sim_nao($a['publicado']), 'versao' => (string) $a['versao']] + $a;
    $mudou = diferencas($legivel($atual), $legivel($dados), [
        'titulo'    => 'Título',
        'versao'    => 'Versão',
        'resumo'    => 'Resumo',
        'conteudo'  => 'Descrição detalhada',
        'publicado' => 'Publicada',
    ]);
    if ($mudou) {
        auditar('atualizacao', 'Atualização editada', 'Anúncio ' . atualizacao_rotulo($dados) . ': ' . implode(', ', array_keys($mudou)) . ' alterado(s)', $id, $mudou);
    }
}

function atualizacao_publicar(array $atualizacao): void
{
    db_executar('UPDATE atualizacoes SET publicado = 1, publicado_em = COALESCE(publicado_em, NOW()) WHERE id = ?', [$atualizacao['id']]);
    auditar('atualizacao', 'Atualização publicada', 'Anúncio ' . atualizacao_rotulo($atualizacao), (int) $atualizacao['id']);
}

function atualizacao_despublicar(array $atualizacao): void
{
    db_executar('UPDATE atualizacoes SET publicado = 0 WHERE id = ?', [$atualizacao['id']]);
    auditar('atualizacao', 'Atualização despublicada', 'Anúncio ' . atualizacao_rotulo($atualizacao) . ' voltou para rascunho', (int) $atualizacao['id']);
}

/** Apaga quem já viu e reinicia a janela do popup, como se fosse publicada agora. */
function atualizacao_reanunciar(array $atualizacao): void
{
    $id = (int) $atualizacao['id'];
    transacao(function () use ($id) {
        db_executar('DELETE FROM atualizacoes_vistas WHERE atualizacao_id = ?', [$id]);
        db_executar('UPDATE atualizacoes SET publicado = 1, publicado_em = NOW() WHERE id = ?', [$id]);
    });
    auditar('atualizacao', 'Atualização anunciada novamente', 'Anúncio ' . atualizacao_rotulo($atualizacao) . ': aviso e popup de 24h reiniciados para todos', $id);
}

function atualizacao_excluir(array $atualizacao): void
{
    db_executar('DELETE FROM atualizacoes WHERE id = ?', [$atualizacao['id']]);
    auditar('atualizacao', 'Atualização excluída', 'Anúncio ' . atualizacao_rotulo($atualizacao) . ' excluído', (int) $atualizacao['id']);
}

function atualizacao_popup_ate(array $atualizacao): int
{
    return strtotime((string) $atualizacao['publicado_em']) + ATUALIZACAO_POPUP_HORAS * 3600;
}

/**
 * Texto simples com formatação mínima, sempre escapado:
 * "## Título" vira subtítulo, "- item" vira lista, linha em branco separa parágrafos.
 */
function texto_formatado(string $texto): string
{
    $html = '';
    $lista = false;
    $paragrafo = [];
    $fecharParagrafo = function () use (&$paragrafo, &$html): void {
        if ($paragrafo) {
            $html .= '<p>' . implode('<br>', $paragrafo) . '</p>';
            $paragrafo = [];
        }
    };

    foreach (preg_split('/\R/', $texto) as $linha) {
        $linha = rtrim($linha);
        if (preg_match('/^\s*[-*•]\s+(.+)$/u', $linha, $m)) {
            $fecharParagrafo();
            if (!$lista) {
                $html .= '<ul>';
                $lista = true;
            }
            $html .= '<li>' . e($m[1]) . '</li>';
            continue;
        }
        if ($lista) {
            $html .= '</ul>';
            $lista = false;
        }
        if (preg_match('/^##\s*(.+)$/u', $linha, $m)) {
            $fecharParagrafo();
            $html .= '<h3>' . e($m[1]) . '</h3>';
        } elseif (trim($linha) === '') {
            $fecharParagrafo();
        } else {
            $paragrafo[] = e($linha);
        }
    }
    if ($lista) {
        $html .= '</ul>';
    }
    $fecharParagrafo();

    return '<div class="texto-formatado">' . $html . '</div>';
}
