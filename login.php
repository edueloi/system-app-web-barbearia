<?php
// login.php (Na raiz do projeto)

include 'includes/db.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já estiver logado, redireciona para o dashboard (assumindo que seja em pages/dashboard.php)
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    header('Location: pages/dashboard.php');
    exit;
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        // Sucesso no login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        // Redireciona para o dashboard
        header('Location: pages/dashboard.php');
        exit;
    } else {
        // Salva mensagem em sessão e faz redirect para evitar reenvio
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --bg-body: #f1f5f9; 
            --text-dark: #1e293b; 
            --text-gray: #64748b;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 90%;
            max-width: 400px;
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .logo {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .subtitle {
            color: var(--text-gray);
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
        }
        .link-footer {
            display: block;
            margin-top: 20px;
            color: var(--primary);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">Salão Top</div>
    <p class="subtitle">Acesso ao Painel do Profissional</p>

    <?php if ($mensagem): ?>
        <div class="error-message"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <input type="email" name="email" class="form-control" placeholder="Seu e-mail" required>
        </div>
        <div class="form-group">
            <input type="password" name="senha" class="form-control" placeholder="Sua senha" required>
        </div>
        <button type="submit" class="btn-submit">Aceder</button>
    </form>
    
    <a href="recuperar_senha.php" class="link-footer">Esqueci a minha senha</a>
    <a href="cadastro.php" class="link-footer" style="margin-top:10px; font-weight:600;">Criar nova conta (Cadastro Rápido)</a>
</div>

</body>
</html>