<?php
// testar_smtp.php - Teste detalhado de SMTP
session_start();
require_once __DIR__ . '/includes/db.php';

// Pega usuário logado
$userLogadoId = $_SESSION['user_id'] ?? 2;
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userLogadoId]);
$user = $stmt->fetch();

echo "<html><head><meta charset='UTF-8'><title>Teste SMTP</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f3f4f6;}";
echo ".box{background:white;padding:20px;border-radius:8px;margin:10px 0;box-shadow:0 2px 8px rgba(0,0,0,0.1);}";
echo ".success{color:#10b981;font-weight:bold;}.error{color:#ef4444;font-weight:bold;}";
echo "pre{background:#1e293b;color:#e2e8f0;padding:15px;border-radius:8px;overflow:auto;}";
echo "</style></head><body>";

echo "<h1>🧪 Teste Detalhado de SMTP</h1>";

echo "<div class='box'>";
echo "<h2>📧 Destinatário</h2>";
echo "<p><strong>Nome:</strong> " . htmlspecialchars($user['nome']) . "</p>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
echo "</div>";

echo "<div class='box'>";
echo "<h2>🔧 Configurações SMTP</h2>";
echo "<pre>";
echo "Servidor: salao.develoi.com\n";
echo "Porta: 465 (SSL)\n";
echo "Usuário: contato@salao.develoi.com\n";
echo "Senha: [CONFIGURADA]\n";
echo "Remetente: contato@salao.develoi.com";
echo "</pre>";
echo "</div>";

echo "<div class='box'>";
echo "<h2>🚀 Iniciando envio...</h2>";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Ativa modo debug VERBOSE
    $mail->SMTPDebug = 2; // 0=off, 1=client, 2=server
    $mail->Debugoutput = function($str, $level) {
        echo "<div style='color:#94a3b8;font-size:12px;margin:2px 0;'>" . htmlspecialchars($str) . "</div>";
    };

    // Configurações SMTP
    $mail->isSMTP();
    $mail->Host       = 'salao.develoi.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'contato@salao.develoi.com';
    $mail->Password   = 'Edu@06051992';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    
    // Timeout aumentado
    $mail->Timeout = 30;
    
    // Remetente
    $mail->setFrom('contato@salao.develoi.com', 'Salão Develoi');
    $mail->addReplyTo('contato@salao.develoi.com', 'Salão Develoi');
    
    // Destinatário
    $mail->addAddress($user['email'], $user['nome']);
    
    // Conteúdo
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = '🧪 TESTE SMTP - ' . date('H:i:s');
    $mail->Body = '
    <html>
    <body style="font-family:Arial;padding:20px;">
        <h1 style="color:#6366f1;">✅ Teste de SMTP</h1>
        <p>Este email foi enviado em <strong>' . date('d/m/Y H:i:s') . '</strong></p>
        <p>Para: <strong>' . htmlspecialchars($user['nome']) . '</strong></p>
        <p>Email: <strong>' . htmlspecialchars($user['email']) . '</strong></p>
        <hr>
        <p style="color:#64748b;font-size:12px;">Sistema Salão Develoi</p>
    </body>
    </html>';
    
    $mail->AltBody = 'Teste de email enviado em ' . date('d/m/Y H:i:s');
    
    echo "<hr><h3>📤 Enviando...</h3>";
    
    $mail->send();
    
    echo "<div style='background:#d1fae5;color:#065f46;padding:15px;border-radius:8px;margin:15px 0;'>";
    echo "<h3 class='success'>✅ EMAIL ENVIADO COM SUCESSO!</h3>";
    echo "<p>Destinatário: <strong>" . htmlspecialchars($user['email']) . "</strong></p>";
    echo "<p>Horário: " . date('H:i:s') . "</p>";
    echo "</div>";
    
    echo "<div style='background:#fef3c7;color:#92400e;padding:15px;border-radius:8px;margin:15px 0;'>";
    echo "<h4>⚠️ IMPORTANTE:</h4>";
    echo "<ul>";
    echo "<li>Verifique sua caixa de entrada</li>";
    echo "<li>Verifique a pasta <strong>SPAM/LIXO ELETRÔNICO</strong></li>";
    echo "<li>Verifique a pasta <strong>PROMOÇÕES</strong> (Gmail)</li>";
    echo "<li>Pode demorar alguns minutos para chegar</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin:15px 0;'>";
    echo "<h3 class='error'>❌ ERRO AO ENVIAR EMAIL</h3>";
    echo "<p><strong>Mensagem de erro:</strong></p>";
    echo "<pre>" . htmlspecialchars($mail->ErrorInfo) . "</pre>";
    echo "<p><strong>Exceção:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</div>";

echo "<div class='box'>";
echo "<h2>🔍 Possíveis Problemas</h2>";
echo "<ul>";
echo "<li><strong>Senha incorreta:</strong> Verifique a senha no arquivo includes/mailer.php</li>";
echo "<li><strong>Porta bloqueada:</strong> Firewall pode estar bloqueando porta 465</li>";
echo "<li><strong>SSL/TLS:</strong> Servidor pode não suportar SSL na porta 465</li>";
echo "<li><strong>Servidor SMTP:</strong> salao.develoi.com pode não aceitar conexões SMTP</li>";
echo "<li><strong>Rate limit:</strong> Servidor pode ter limite de envios</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align:center;margin-top:30px;'>";
echo "<a href='verificar_email.php' style='color:#6366f1;'>← Voltar</a>";
echo "</p>";

echo "</body></html>";
?>
