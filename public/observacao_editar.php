<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_login();
if (!is_post()) {
    redirect(pagina_inicial());
}
csrf_verificar();

// Volta para a tela de onde veio; só caminhos internos simples são aceitos.
$voltar = input('voltar');
if (!preg_match('#^[a-z_]+(/[a-z_]+)?\.php(\?[\w=&%.\-]*)?$#', $voltar)) {
    $voltar = pagina_inicial();
}

try {
    observacao_editar(input_int('id'), input('observacao'), $usuario);
    flash('sucesso', 'Observação editada.');
} catch (RuntimeException $ex) {
    flash('erro', $ex->getMessage());
}

redirect($voltar);
