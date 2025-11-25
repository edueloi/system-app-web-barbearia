<?php
// Página de login simples
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    // Exemplo: usuário e senha fixos
    if ($user === 'barbeiro' && $pass === '1234') {
        $_SESSION['user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $erro = 'Usuário ou senha inválidos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (!empty($erro)) echo '<p style="color:red;">'.$erro.'</p>'; ?>
        <form method="post">
            <input type="text" name="user" placeholder="Usuário" required><br>
            <input type="password" name="pass" placeholder="Senha" required><br>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
