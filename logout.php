<?php
// logout.php (Na raiz do projeto)

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroi todas as variáveis de sessão
$_SESSION = array();

// Se for preciso, destrói o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão
session_destroy();

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd
    ? '/login' // em produção usa rota amigável
    : '/karen_site/controle-salao/login.php';

// Redireciona para a página de login
header("Location: {$loginUrl}");
exit;
?>