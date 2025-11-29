<?php
// cadastro.php (Na raiz do projeto)

include 'includes/db.php';

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    if (strlen($senha) < 6) {
        $_SESSION['cadastro_msg'] = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        // Permitir múltiplos cadastros
        $existe = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $existe->execute([$email]);
        if ($existe->fetchColumn() > 0) {
            $_SESSION['cadastro_msg'] = 'Já existe um usuário com este e-mail.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)")->execute([$nome, $email, $hash]);
            $_SESSION['cadastro_msg'] = 'Cadastro realizado com sucesso! Faça login.';
            $_SESSION['cadastro_tipo'] = 'success';
        }
    }
    // 🔹 Descobre se está em produção (salao.develoi.com) ou local
    $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    $cadastroUrl = $isProd ? '/cadastro' : '/karen_site/controle-salao/cadastro.php';
    header("Location: {$cadastroUrl}");
    exit;
}

// Recupera mensagem de sessão (se houver)
if (isset($_SESSION['cadastro_msg'])) {
    $mensagem = $_SESSION['cadastro_msg'];
    unset($_SESSION['cadastro_msg']);
    $tipoMensagem = $_SESSION['cadastro_tipo'] ?? '';
    unset($_SESSION['cadastro_tipo']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Salão Develoi</title>
    <?php /* Incluiria o CSS aqui ou nos estilos inline */ ?>
    <style>
        :root { /* ... (mesmas variáveis de estilo) ... */ --primary: #6366f1; --bg-body: #f1f5f9; --text-dark: #1e293b; --text-gray: #64748b; --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { width: 90%; max-width: 400px; background: white; padding: 40px 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); text-align: center; }
        .logo { color: var(--primary); font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
        .subtitle { color: var(--text-gray); margin-bottom: 30px; font-size: 0.9rem; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; margin-top: 15px; }
        .link-footer { display: block; margin-top: 20px; color: var(--primary); font-size: 0.9rem; text-decoration: none; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; }
        .error-message { background: #fee2e2; color: #dc2626; }
        .success-message { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">Salão Develoi</div>
    <p class="subtitle">Criar Conta Principal</p>

    <?php if ($mensagem): ?>
        <div class="message <?php echo ($tipoMensagem ?? '') === 'success' ? 'success-message' : 'error-message'; ?>"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <input type="text" name="nome" class="form-control" placeholder="Seu Nome Completo" required>
        </div>
        <div class="form-group">
            <input type="email" name="email" class="form-control" placeholder="Seu E-mail" required>
        </div>
        <div class="form-group">
            <input type="password" name="senha" class="form-control" placeholder="Crie uma Senha (mín. 6 caracteres)" required>
        </div>
        <button type="submit" class="btn-submit">Cadastrar</button>
    </form>
    
    <a href="login.php" class="link-footer">Já tenho uma conta</a>
</div>

</body>
</html>