<?php
// nova_senha.php

include 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';

$mensagem = '';
$tipo_msg = '';

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

                    $mensagem = 'Senha alterada com sucesso! Você já pode fazer login.';
                    $tipo_msg = 'sucesso';

                    // Opcional: já redirecionar após alguns segundos
                    // header("Refresh: 3; URL={$loginUrl}");
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha • Develoi Agenda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">

            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h5 mb-3 text-center">Definir nova senha</h1>

                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?php echo ($tipo_msg == 'erro' ? 'danger' : 'success'); ?>">
                            <?php echo $mensagem; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tipo_msg !== 'erro' && !empty($user) && $user['token_validade'] >= date('Y-m-d H:i:s')): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nova senha</label>
                                <input type="password" name="senha" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar nova senha</label>
                                <input type="password" name="senha_confirm" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Salvar nova senha</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-secondary btn-sm">
                                Voltar ao login
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
