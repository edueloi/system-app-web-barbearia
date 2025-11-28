<?php
// Redireciona automaticamente para a tela de login
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';
header("Location: {$loginUrl}");
exit;
