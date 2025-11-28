<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | Painel' : 'Painel de Controle'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>

<nav class="navbar mb-4">
    <div class="container main-container my-0 d-flex justify-content-between align-items-center">
        <div>
            <a class="navbar-brand" href="#">
                <div class="logo-box">AD</div>
                <div>
                    <span>Painel do Profissional</span>
                    <div class="navbar-subtitle">Gestão de agendamentos e serviços</div>
                </div>
            </a>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <span style="display:block; font-size:0.85rem; font-weight:600;">Profissional</span>
                <span style="display:block; font-size:0.75rem; color:#6b7280;">Logado</span>
            </div>
            <a href="/logout.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-right me-1"></i> Sair
            </a>
        </div>
    </div>
</nav>

<div class="main-container">
