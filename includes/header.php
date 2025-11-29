<?php
require_once __DIR__ . '/config.php';
// Inicia a sessão globalmente (assim não precisas repetir em todas as páginas)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Título padrão da página (podes mudar antes do include se quiseres)
if (!isset($pageTitle)) {
    $pageTitle = 'Salão Develoi - Gestão';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>img/logo-azul.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>img/logo-azul.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- VARIÁVEIS GERAIS DO TEMA (App Style) --- */
        :root {
            --primary: #6366f1;       /* Roxo Indigo */
            --primary-hover: #4f46e5;
            --bg-body: #f1f5f9;       /* Cinza azulado muito claro */
            --text-dark: #1e293b;     /* Quase preto */
            --text-gray: #64748b;     /* Cinza médio */
            --white: #ffffff;
            --danger: #ef4444;
            --success: #22c55e;
            --radius: 16px;           /* Bordas arredondadas estilo iOS */
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased; 
        }

        /* Classe utilitária para o conteúdo principal */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 30px; 
        }

        /* Estilo padrão de Card */
        .app-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
    </style>
</head>
<body>