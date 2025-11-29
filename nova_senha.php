<?php
// nova_senha.php

include 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';

$mensagem   = '';
$tipo_msg   = '';
$tokenValido = false;
$user       = null;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $mensagem = 'Link de recuperação inválido.';
    $tipo_msg = 'erro';
} else {
    // Busca usuário pelo token
    $stmt = $pdo->prepare("
        SELECT id, token_validade 
        FROM usuarios 
        WHERE token_recuperacao = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $mensagem = 'Este link de recuperação é inválido ou já foi utilizado.';
        $tipo_msg = 'erro';
    } else {
        // Verifica se token está expirado
        $agora = date('Y-m-d H:i:s');
        if (empty($user['token_validade']) || $user['token_validade'] < $agora) {
            $mensagem = 'Este link de recuperação expirou. Solicite uma nova recuperação de senha.';
            $tipo_msg = 'erro';
        } else {
            $tokenValido = true;

            // Token é válido → se for POST, troca a senha
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $senha        = $_POST['senha'] ?? '';
                $senhaConfirm = $_POST['senha_confirm'] ?? '';

                if (empty($senha) || empty($senhaConfirm)) {
                    $mensagem = 'Preencha os dois campos de senha.';
                    $tipo_msg = 'erro';
                } elseif ($senha !== $senhaConfirm) {
                    $mensagem = 'As senhas não conferem.';
                    $tipo_msg = 'erro';
                } elseif (strlen($senha) < 6) {
                    $mensagem = 'A senha deve ter pelo menos 6 caracteres.';
                    $tipo_msg = 'erro';
                } else {
                    // Atualiza a senha e invalida o token (1 uso só)
                    $hash = password_hash($senha, PASSWORD_DEFAULT);

                    $upd = $pdo->prepare("
                        UPDATE usuarios
                        SET senha = ?, token_recuperacao = NULL, token_validade = NULL
                        WHERE id = ?
                    ");
                    $upd->execute([$hash, $user['id']]);

                    $mensagem    = 'Senha alterada com sucesso! Você já pode fazer login.';
                    $tipo_msg    = 'sucesso';
                    $tokenValido = false; // Não mostra mais o formulário
                }
            }
        }
    }
}

// Favicon dinâmico igual aos outros arquivos
if ($isProd) {
    $faviconUrl = 'https://salao.develoi.com/img/logo-azul.png';
} else {
    $host = $_SERVER['HTTP_HOST'];
    $faviconUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha • Develoi Agenda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="icon" href="<?php echo $faviconUrl; ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo $faviconUrl; ?>" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #6366f1;
            --secondary: #ec4899;
            --accent: #8b5cf6;
            --dark: #0f172a;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            display: flex;
        }

        /* Fundo animado */
        .animated-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(-45deg, #0f172a, #1e1b4b, #312e81, #4c1d95);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .bokeh {
            position: fixed; width: 40vw; height: 40vw; border-radius: 50%; filter: blur(80px); opacity: 0.4; z-index: -1;
            animation: float 20s infinite ease-in-out alternate;
        }
        .b1 { top: -10%; left: -10%; background: var(--primary); }
        .b2 { bottom: -10%; right: -10%; background: var(--secondary); animation-delay: -5s; }

        @keyframes float {
            0%   { transform: translate(0, 0); }
            100% { transform: translate(30px, -30px); }
        }

        /* Layout */
        .split-container {
            width: 100%; height: 100%;
            display: grid; grid-template-columns: 450px 1fr;
        }

        /* Painel esquerdo (formulário) */
        .panel-left {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            padding: 40px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            box-shadow: 10px 0 30px rgba(0,0,0,0.12);
            position: relative; z-index: 10;
        }

        .content-box {
            width: 100%; max-width: 340px;
        }

        .mini-logo {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-family: 'Outfit'; font-size: 1.5rem;
            margin: 0 auto 20px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 1.5rem;
            text-align: center;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 0.9rem;
            text-align: center;
            line-height: 1.5;
        }

        .input-group-custom { position: relative; margin-bottom: 18px; }

        .input-custom {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 99px;
            font-size: 0.9rem;
            background: #f8fafc;
            transition: all 0.3s;
        }
        .input-custom:focus {
            background: #ffffff;
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.12);
        }

        .input-icon {
            position: absolute; left: 18px; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: 0.2s;
        }
        .input-custom:focus + .input-icon {
            color: var(--primary);
        }

        .btn-primary-full {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 999px;
            background: var(--dark);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15,23,42,0.25);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: 0.25s;
        }
        .btn-primary-full:hover {
            background: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15,23,42,0.3);
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 20px;
            text-decoration: none;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover {
            color: var(--primary);
            transform: translateX(-3px);
        }

        .alert-custom {
            border-radius: 16px;
            font-size: 0.85rem;
            padding: 12px 16px;
            margin-bottom: 20px;
            border: none;
        }
        .alert-erro    { background: #fef2f2; color: #b91c1c; }
        .alert-sucesso { background: #f0fdf4; color: #15803d; }

        /* Painel direito */
        .panel-right {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1200px;
        }

        .info-card {
            width: 380px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 38px;
            text-align: center;
            color: #ffffff;
            transform: rotateY(-6deg);
            animation: hoverCard 7s ease-in-out infinite;
        }

        @keyframes hoverCard {
            0%,100% { transform: rotateY(-6deg) translateY(0); }
            50%     { transform: rotateY(-6deg) translateY(-14px); }
        }

        .icon-circle {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 20%, #e0e7ff, #6366f1);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            margin: 0 auto 22px;
            box-shadow: 0 10px 30px rgba(129,140,248,0.6);
            position: relative;
        }
        .badge-small {
            position: absolute; bottom: -6px; right: -6px;
            width: 30px; height: 30px; border-radius: 50%;
            background: #22c55e;
            border: 3px solid rgba(15,23,42,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
        }

        .info-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 10px;
        }
        .info-text {
            font-size: 0.95rem;
            line-height: 1.6;
            opacity: 0.9;
        }

        @media (max-width: 991px) {
            .split-container { grid-template-columns: 1fr; }
            .panel-right { display: none; }
            .panel-left { width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="animated-bg"></div>
    <div class="bokeh b1"></div>
    <div class="bokeh b2"></div>

    <div class="split-container">
        <!-- Lado Esquerdo: formulário -->
        <div class="panel-left animate__animated animate__fadeInLeft">
            <div class="content-box">
                <div class="mini-logo">D</div>

                <h1>Definir nova senha</h1>
                <p class="subtitle">
                    Crie uma senha forte para manter o acesso ao <strong>Develoi Agenda</strong> seguro.
                </p>

                <?php if ($mensagem): ?>
                    <div class="alert-custom <?php echo ($tipo_msg === 'erro') ? 'alert-erro' : 'alert-sucesso'; ?> d-flex align-items-center gap-2 animate__animated animate__pulse">
                        <?php if ($tipo_msg === 'erro'): ?>
                            <i class="fa-solid fa-circle-exclamation"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php endif; ?>
                        <span><?php echo $mensagem; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($tokenValido && $tipo_msg !== 'sucesso'): ?>
                    <form method="POST">
                        <div class="input-group-custom">
                            <input type="password" name="senha" class="input-custom" placeholder="Nova senha" required>
                            <i class="fa-solid fa-lock input-icon"></i>
                        </div>
                        <div class="input-group-custom">
                            <input type="password" name="senha_confirm" class="input-custom" placeholder="Confirmar nova senha" required>
                            <i class="fa-solid fa-lock-keyhole input-icon"></i>
                        </div>

                        <button type="submit" class="btn-primary-full">
                            Salvar nova senha
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo $loginUrl; ?>" class="back-link">
                            <i class="fa-solid fa-arrow-left"></i> Voltar ao login
                        </a>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 40px; text-align: center; opacity: 0.6; font-size: 0.75rem;">
                    &copy; Develoi Agenda
                </div>
            </div>
        </div>

        <!-- Lado Direito: card informativo -->
        <div class="panel-right animate__animated animate__fadeIn">
            <div class="info-card">
                <div class="icon-circle">
                    <i class="fa-solid fa-key"></i>
                    <div class="badge-small">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>
                <div class="info-title">Senha nova, acesso protegido</div>
                <p class="info-text">
                    Use letras maiúsculas, minúsculas, números e símbolos para criar uma senha forte.
                    O link de redefinição é válido por <strong>2 horas</strong> para garantir sua segurança.
                </p>
            </div>
        </div>
    </div>

</body>
</html>
