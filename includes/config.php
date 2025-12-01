<?php
// config.php

// Verifica se está rodando no Localhost
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // CAMINHO DO LOCALHOST (ajustado para seu projeto)
    define('BASE_URL', 'http://localhost/karen_site/controle-salao/');
} else {
    // CAMINHO DO SERVIDOR (Produção)
    define('BASE_URL', 'https://salao.develoi.com/');
}

// --- CHAVES DE CONFIGURAÇÃO DE SESSÃO ---
// Ativa/desativa auto logout por inatividade (true/false)
define('AUTO_LOGOUT', true);
// Tempo de inatividade em minutos
define('AUTO_LOGOUT_MINUTES', 30);
?>