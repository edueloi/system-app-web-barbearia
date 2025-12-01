<?php
// testar_email.php - Teste simplificado de SMTP
require_once __DIR__ . '/includes/mailer.php';

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'>";
echo "<title>Teste de Email - Salão Develoi</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 40px; background: #f3f4f6; }
    .box { background: white; padding: 30px; border-radius: 12px; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .success { color: #10b981; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow: auto; font-size: 12px; }
    .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🧪 Teste de Envio SMTP</h1>";
echo "<p><strong>Horário:</strong> " . date('d/m/Y H:i:s') . "</p>";

// 🔹 COLOQUE SEU EMAIL REAL AQUI
$emailTeste = 'edueloi.ee@gmail.com';

echo "<p><strong>Destinatário:</strong> $emailTeste</p>";
echo "<hr>";

// Template HTML simples
$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:40px;font-family:Arial,sans-serif;background:#f3f4f6;">
    <div style="max-width:600px;margin:0 auto;background:white;padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        <h1 style="color:#6366f1;">🧪 Teste de SMTP</h1>
        <p style="color:#475569;font-size:16px;">
            Este é um email de teste enviado em <strong>' . date('d/m/Y H:i:s') . '</strong>
        </p>
        <p style="color:#475569;font-size:16px;">
            Se você recebeu este email, o sistema <strong>Salão Develoi</strong> está funcionando! ✅
        </p>
        <div style="margin:30px 0;padding:20px;background:#eef2ff;border-left:4px solid #6366f1;border-radius:8px;">
            <p style="margin:0;color:#4f46e5;font-size:14px;">
                <strong>📧 Configurações SMTP:</strong><br>
                Host: mail.salao.develoi.com<br>
                Porta: 465 (SSL)<br>
                Remetente: contato@salao.develoi.com
            </p>
        </div>
        <hr>
        <p style="color:#94a3b8;font-size:12px;text-align:center;">
            <strong>Email automático - Não responder</strong><br>
            Sistema Salão Develoi
        </p>
    </div>
</body>
</html>';

echo "<h2>📤 Enviando email de teste...</h2>";

try {
    $resultado = sendMailDeveloi(
        $emailTeste,
        'Teste Develoi',
        '🧪 Teste SMTP - ' . date('H:i:s'),
        $htmlBody
    );
    
    if ($resultado) {
        echo "<div style='background:#d1fae5;color:#065f46;padding:20px;border-radius:8px;margin:20px 0;'>";
        echo "<h3 class='success'>✅ EMAIL ENVIADO COM SUCESSO!</h3>";
        echo "<p><strong>Para:</strong> $emailTeste</p>";
        echo "<p><strong>Horário:</strong> " . date('H:i:s') . "</p>";
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<h4>⚠️ IMPORTANTE - Verifique:</h4>";
        echo "<ul>";
        echo "<li>Caixa de entrada principal</li>";
        echo "<li>Pasta <strong>SPAM / LIXO ELETRÔNICO</strong></li>";
        echo "<li>Pasta <strong>PROMOÇÕES</strong> (Gmail)</li>";
        echo "<li>Pasta <strong>ATUALIZAÇÕES</strong> (Gmail)</li>";
        echo "<li>Pode demorar alguns minutos para chegar</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<div style='background:#fee2e2;color:#991b1b;padding:20px;border-radius:8px;margin:20px 0;'>";
        echo "<h3 class='error'>❌ FALHA AO ENVIAR EMAIL</h3>";
        echo "<p>O PHPMailer retornou <strong>false</strong>.</p>";
        echo "<p>Verifique o <strong>error_log</strong> no cPanel para ver os detalhes.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background:#fee2e2;color:#991b1b;padding:20px;border-radius:8px;margin:20px 0;'>";
    echo "<h3 class='error'>❌ EXCEPTION CAPTURADA</h3>";
    echo "<p><strong>Mensagem:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<h3>🔍 Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Se mostrou <strong class='success'>✅ SUCESSO</strong> mas o email não chegou:
    <ul>
        <li>Verifique TODAS as pastas do Gmail (SPAM principalmente)</li>
        <li>Aguarde alguns minutos (pode demorar)</li>
        <li>Veja o <code>error_log</code> no cPanel para logs do SMTP</li>
    </ul>
</li>";
echo "<li>Se mostrou <strong class='error'>❌ FALHA</strong>:
    <ul>
        <li>Abra o <code>error_log</code> no cPanel</li>
        <li>Procure por <code>SMTP DEBUG</code> ou <code>Exception</code></li>
        <li>Verifique se Host/Porta/Senha estão corretos</li>
    </ul>
</li>";
echo "</ol>";

echo "<hr style='margin:30px 0;'>";
echo "<p style='text-align:center;'>";
echo "<a href='.' style='color:#6366f1;font-weight:bold;'>← Voltar para o sistema</a>";
echo "</p>";

echo "</div>";
echo "</body></html>";
?>
