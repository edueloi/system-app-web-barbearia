<?php
// testar_email.php - Script para testar envio de email

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// Pega dados do primeiro usuário
$stmt = $pdo->query("SELECT * FROM usuarios LIMIT 1");
$usuario = $stmt->fetch();

if (!$usuario) {
    die('Nenhum usuário encontrado no banco');
}

echo "<h2>Teste de Envio de Email</h2>";
echo "<p><strong>Nome:</strong> " . htmlspecialchars($usuario['nome']) . "</p>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($usuario['email'] ?? 'NÃO CADASTRADO') . "</p>";

if (empty($usuario['email'])) {
    die('<p style="color:red;"><strong>ERRO:</strong> Este usuário não tem email cadastrado no banco de dados!</p>
         <p>Acesse o <a href="pages/perfil/perfil.php">perfil</a> e cadastre um email válido.</p>');
}

// Template simples de teste
$emailHTML = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teste de Email</title>
</head>
<body style="margin:0;padding:40px;font-family:Arial,sans-serif;background:#f3f4f6;">
    <div style="max-width:600px;margin:0 auto;background:white;padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        <h1 style="color:#6366f1;margin:0 0 20px;">🧪 Email de Teste</h1>
        <p style="color:#475569;font-size:16px;line-height:1.6;">
            Olá <strong>' . htmlspecialchars($usuario['nome']) . '</strong>,
        </p>
        <p style="color:#475569;font-size:16px;line-height:1.6;">
            Este é um email de teste do sistema <strong>Salão Develoi</strong>.
        </p>
        <p style="color:#475569;font-size:16px;line-height:1.6;">
            Se você recebeu este email, significa que o sistema de envio está funcionando corretamente! ✅
        </p>
        <div style="margin:30px 0;padding:20px;background:#eef2ff;border-left:4px solid #6366f1;border-radius:8px;">
            <p style="margin:0;color:#4f46e5;font-size:14px;">
                <strong>📧 Configurações SMTP:</strong><br>
                Servidor: salao.develoi.com<br>
                Porta: 465 (SSL)<br>
                Remetente: contato@salao.develoi.com
            </p>
        </div>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:30px 0;">
        <p style="color:#94a3b8;font-size:12px;text-align:center;margin:0;">
            <strong>Email automático - Não responder</strong><br>
            Enviado de <a href="https://salao.develoi.com" style="color:#6366f1;">salao.develoi.com</a>
        </p>
    </div>
</body>
</html>';

echo "<h3>🚀 Enviando email de teste...</h3>";

try {
    $enviou = sendMailDeveloi(
        $usuario['email'],
        $usuario['nome'],
        '🧪 Teste - Sistema Salão Develoi',
        $emailHTML
    );
    
    if ($enviou) {
        echo '<p style="color:green;font-size:18px;"><strong>✅ EMAIL ENVIADO COM SUCESSO!</strong></p>';
        echo '<p>Verifique a caixa de entrada de: <strong>' . htmlspecialchars($usuario['email']) . '</strong></p>';
        echo '<p style="color:#f59e0b;">⚠️ Não esqueça de verificar a pasta de SPAM/LIXO ELETRÔNICO</p>';
    } else {
        echo '<p style="color:red;font-size:18px;"><strong>❌ FALHA NO ENVIO</strong></p>';
        echo '<p>O email não pôde ser enviado. Verifique os logs de erro.</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color:red;font-size:18px;"><strong>❌ ERRO:</strong></p>';
    echo '<pre style="background:#fee;padding:15px;border-radius:8px;overflow:auto;">';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';
}

echo '<hr style="margin:30px 0;">';
echo '<p><a href="agendar.php?user=' . $usuario['id'] . '" style="color:#6366f1;">← Voltar para agendamento</a></p>';
?>
