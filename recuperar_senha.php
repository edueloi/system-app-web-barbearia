<?php
// recuperar_senha.php (Na raiz do projeto)

// Esta página é uma SIMULAÇÃO. A lógica real exigiria PHPMailer e um servidor SMTP.

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = 'Se o e-mail estiver em nosso cadastro, você receberá instruções de recuperação em breve. (Funcionalidade de e-mail desativada na simulação).';
    $tipoMensagem = 'info';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Salão Top</title>
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
        .info-message { background: #e0e7ff; color: #4338ca; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">Salão Top</div>
    <p class="subtitle">Recuperação de Senha</p>

    <?php if ($mensagem): ?>
        <div class="message info-message"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <form method="POST">
        <p style="font-size:0.9rem; color:var(--text-gray); margin-bottom:20px;">
            Digite o e-mail cadastrado para receber as instruções de recuperação.
        </p>
        <div class="form-group">
            <input type="email" name="email" class="form-control" placeholder="Seu e-mail" required>
        </div>
        <button type="submit" class="btn-submit">Enviar Link</button>
    </form>
    
    <a href="login.php" class="link-footer">Voltar ao Login</a>
</div>

</body>
</html>