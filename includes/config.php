<?php
// config.php

// Verifica se está rodando no Localhost
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // CAMINHO DO LOCALHOST (Ajuste o nome da pasta do seu projeto aqui)
    // Se no seu PC você acessa localhost/salao-projeto, coloque isso abaixo
    define('BASE_URL', 'http://localhost/NOME_DA_SUA_PASTA_NO_XAMPP/'); 
} else {
    // CAMINHO DO SERVIDOR (Produção)
    define('BASE_URL', 'http://salao.develoi.com/');
}
?>