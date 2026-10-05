<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_login();
$codigo = normalizar_codigo(input('codigo'));

if ($usuario['setor'] === 'erp') {
    redirect('consulta.php?codigo=' . urlencode($codigo));
}

$equipamento = $codigo !== '' ? equipamento_por_codigo($codigo) : null;
if ($equipamento) {
    redirect('equipamentos/ver.php?id=' . $equipamento['id']);
}

redirect('manutencoes/nova.php?codigo=' . urlencode($codigo));
