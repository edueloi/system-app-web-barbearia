<?php
// login.php (Na raiz do projeto)

include 'includes/db.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("SELECT id, nome, senha, ativo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        if (isset($user['ativo']) && $user['ativo'] == 0) {
            $_SESSION['login_erro'] = 'Seu acesso está inativo. Fale com o administrador.';
            header('Location: login.php');
            exit;
        }
        // Sucesso no login
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['nome'];

        header('Location: pages/dashboard.php');
        exit;
    } else {
        $_SESSION['login_erro'] = 'E-mail ou senha incorretos. Tente novamente.';
        header('Location: login.php');
        exit;
    }
}

// Recupera mensagem de erro da sessão (se houver)
if (isset($_SESSION['login_erro'])) {
    $mensagem = $_SESSION['login_erro'];
    unset($_SESSION['login_erro']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Salão Top</title>
    <link rel="icon" type="image/png" href="img/logo-azul.png">
    <link rel="shortcut icon" href="img/logo-azul.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4338ca;
            --primary-soft: #eef2ff;
            --text-dark: #1f2933;
            --text-gray: #6b7280;
            --shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Quicksand', 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #e0e7ff 0, #f3f4f6 35%, #eef2ff 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 12px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            padding: 32px 26px 24px;
            border-radius: 26px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top, rgba(99,102,241,0.12), transparent 55%);
            pointer-events: none;
        }

        .login-inner {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .app-icon-wrapper {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }

        .app-icon {
            font-size: 2.2rem;
            color: var(--primary);
        }

        .logo {
            color: var(--text-dark);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .subtitle {
            color: var(--text-gray);
            margin-bottom: 22px;
            font-size: 0.93rem;
            font-weight: 500;
        }

        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 9px 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: left;
        }

        form {
            text-align: left;
        }

        .form-group {
            margin-bottom: 14px;
            position: relative;
        }

        .form-label {
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--text-gray);
            margin-bottom: 4px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.98rem;
            font-family: inherit;
            background: #f9fafb;
            transition: border 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.20);
        }

        .btn-submit {
            width: 100%;
            padding: 12px 0;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.02rem;
            letter-spacing: 0.4px;
            cursor: pointer;
            margin-top: 12px;
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.25);
            transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.15s ease;
        }

        .btn-submit:hover {
            filter: brightness(1.03);
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(79, 70, 229, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 6px 14px rgba(79, 70, 229, 0.23);
        }

        .links-wrapper {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }

        .link-footer {
            color: var(--primary);
            font-size: 0.9rem;
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 0.1px;
            transition: color 0.2s ease;
        }

        .link-footer:hover {
            color: var(--primary-dark);
        }

        .helper-text {
            margin-top: 14px;
            font-size: 0.78rem;
            color: #9ca3af;
            text-align: center;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 24px 18px 18px;
                border-radius: 22px;
            }

            .logo {
                font-size: 1.2rem;
            }

            .subtitle {
                font-size: 0.88rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-inner">
        <div class="app-icon-wrapper" style="background: none; box-shadow: none;">
            <img src="img/logo-azul.png" alt="Logo Salão Top" style="width:54px; height:54px; object-fit:contain; display:block; margin:0 auto; border-radius:12px; background:#eef2ff; padding:4px; box-shadow:0 2px 8px #e0e7ff;">
        </div>
        <div class="logo">Salão Top</div>
        <p class="subtitle">Acesso ao Painel do Profissional</p>

        <?php if ($mensagem): ?>
            <div class="error-message"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="form-group">
                <label class="form-label" for="email">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="seuemail@exemplo.com" required autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label" for="senha">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="Digite sua senha" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">Entrar</button>
        </form>

        <div class="links-wrapper">
            <a href="recuperar_senha.php" class="link-footer">Esqueci minha senha</a>
            <a href="cadastro.php" class="link-footer">Criar nova conta</a>
        </div>

        <div class="helper-text">
            Sistema de agendamento para profissionais de beleza.
        </div>
    </div>
</div>

</body>
</html>
