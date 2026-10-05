<?php

require __DIR__ . '/../src/bootstrap.php';

if (is_post()) {
    csrf_verificar();
    sair();
}

redirect('login.php');
