<?php
// verificar_email.php
session_start();
require_once __DIR__ . '/includes/db.php';

echo "=== TODOS OS USUÁRIOS CADASTRADOS ===\n\n";

$stmt = $pdo->query("SELECT id, nome, email, estabelecimento FROM usuarios ORDER BY id");
$usuarios = $stmt->fetchAll();

foreach ($usuarios as $u) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: " . $u['id'] . "\n";
    echo "Nome: " . $u['nome'] . "\n";
    echo "Estabelecimento: " . ($u['estabelecimento'] ?? 'Não cadastrado') . "\n";
    echo "Email: " . ($u['email'] ?? '❌ NÃO CADASTRADO') . "\n";
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $u['id']) {
        echo "👤 << ESTE É VOCÊ (LOGADO AGORA)\n";
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Pega o usuário da sessão
$userLogadoId = $_SESSION['user_id'] ?? 1;
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userLogadoId]);
$user = $stmt->fetch();

echo "=== USUÁRIO QUE VAI RECEBER O EMAIL ===\n";
echo "ID: " . $user['id'] . "\n";
echo "Nome: " . $user['nome'] . "\n";
echo "Email: " . ($user['email'] ?? '❌ NÃO CADASTRADO') . "\n";
echo "\n";

if (empty($user['email'])) {
    echo "❌ PROBLEMA: Você NÃO tem email cadastrado!\n";
    echo "Por isso não está recebendo as notificações.\n";
    echo "\n";
    echo "SOLUÇÃO: Acesse o PERFIL e cadastre seu email.\n";
} else {
    echo "✅ Email cadastrado: " . $user['email'] . "\n";
    echo "\n";
    echo "Agora vou testar o envio...\n";
    
    require_once __DIR__ . '/includes/mailer.php';
    
    $emailHTML = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family:Arial;padding:40px;background:#f3f4f6;">
        <div style="max-width:600px;margin:0 auto;background:white;padding:30px;border-radius:12px;">
            <h1 style="color:#6366f1;">✅ Teste de Email</h1>
            <p>Olá <strong>' . htmlspecialchars($user['nome']) . '</strong>,</p>
            <p>Este é um email de teste do <strong>Sistema Salão Develoi</strong>.</p>
            <p>Se você recebeu este email, o sistema está funcionando! 🎉</p>
            <hr>
            <p style="color:#94a3b8;font-size:12px;text-align:center;">
                Email automático - Não responder<br>
                Enviado de salao.develoi.com
            </p>
        </div>
    </body>
    </html>';
    
    try {
        $enviou = sendMailDeveloi(
            $user['email'],
            $user['nome'],
            '✅ Teste - Novo Agendamento',
            $emailHTML
        );
        
        if ($enviou) {
            echo "\n✅ EMAIL ENVIADO COM SUCESSO!\n";
            echo "Verifique: " . $user['email'] . "\n";
            echo "⚠️ Olhe também na pasta SPAM/LIXO ELETRÔNICO\n";
        } else {
            echo "\n❌ FALHA no envio\n";
        }
    } catch (Exception $e) {
        echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    }
}
?>
