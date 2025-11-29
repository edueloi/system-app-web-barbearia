<?php
// recuperar_senha.php

include 'includes/db.php';
include 'includes/mailer.php';

// Inicia sessão para gerenciar mensagens
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔹 Lógica de Ambiente (Mesma do Login)
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';

$mensagem = '';
$tipo_msg = ''; // 'erro' ou 'sucesso'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $mensagem = "Por favor, digite seu e-mail.";
        $tipo_msg = 'erro';
    } else {
        // Verifica se o e-mail existe
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 1. Gera um token único
            $token = bin2hex(random_bytes(50));

            // 2. Define validade (2 horas a partir de agora)
            $validade = date('Y-m-d H:i:s', strtotime('+2 hours'));

            // 3. Salva no banco (sempre sobrescreve tokens antigos)
            $update = $pdo->prepare("
                UPDATE usuarios 
                SET token_recuperacao = ?, token_validade = ? 
                WHERE id = ?
            ");
            $update->execute([$token, $validade, $user['id']]);

            // 4. Monta o link de recuperação (ambiente prod x local)
            if ($isProd) {
                $link = "https://salao.develoi.com/nova_senha.php?token=" . urlencode($token);
                $logoUrl = "https://salao.develoi.com/img/logo-D.png";
            } else {
                $host   = $_SERVER['HTTP_HOST'];
                $link   = "http://{$host}/karen_site/controle-salao/nova_senha.php?token=" . urlencode($token);
                $logoUrl = "http://{$host}/karen_site/controle-salao/img/logo-D.png";
            }

            // 5. Corpo do e-mail (template bonito)

            $nomeUsuario = $user['nome'] ?? 'Cliente';
            $subject     = 'Recuperação de senha - Develoi Agenda';
            $year        = date('Y');

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px 12px;">
        <tr>
            <td align="center">

                <!-- CARTÃO CENTRAL -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,.45);">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="padding:20px 26px 16px 26px;background:linear-gradient(135deg,#6366f1,#ec4899);">
                            <table role="presentation" width="100%">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        <div style="display:inline-flex;align-items:center;gap:10px;">
                                            <div style="width:40px;height:40px;border-radius:14px;background:rgba(15,23,42,.18);display:flex;align-items:center;justify-content:center;">
                                                <img src="{$logoUrl}" alt="Develoi Agenda" style="display:block;width:26px;height:26px;object-fit:contain;">
                                            </div>
                                            <div style="font-size:13px;color:#e5e7eb;line-height:1.4;">
                                                <strong style="display:block;color:#f9fafb;font-size:14px;">Develoi Agenda</strong>
                                                <span style="opacity:.9;">Gestão inteligente para salões & barbearias</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;font-size:11px;color:#e5e7eb;opacity:.9;">
                                        Segurança de acesso<br>
                                        <span style="opacity:.9;">Link válido por 2 horas</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CONTEÚDO -->
                    <tr>
                        <td style="padding:24px 26px 8px 26px;">
                            <p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;">
                                Olá, <strong style="color:#111827;">{$nomeUsuario}</strong> 👋
                            </p>

                            <h1 style="margin:0 0 10px 0;font-size:20px;line-height:1.3;color:#111827;">
                                Redefinição de senha
                            </h1>

                            <p style="margin:0 0 10px 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Recebemos uma solicitação para redefinir a sua senha no
                                <strong>Develoi Agenda</strong>.
                            </p>

                            <p style="margin:0 0 18px 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Para continuar com segurança, clique no botão abaixo para criar uma nova senha.
                                Este link é válido por <strong>2 horas</strong> e pode ser utilizado apenas uma vez.
                            </p>

                            <!-- BOTÃO -->
                            <p style="text-align:center;margin:0 0 22px 0;">
                                <a href="{$link}"
                                   style="display:inline-block;padding:13px 28px;border-radius:999px;
                                          background:#111827;color:#ffffff;text-decoration:none;
                                          font-size:14px;font-weight:600;letter-spacing:.01em;
                                          box-shadow:0 12px 30px rgba(15,23,42,.45);">
                                    Redefinir minha senha
                                </a>
                            </p>

                            <!-- TEXTO DO LINK -->
                            <p style="margin:0 0 8px 0;font-size:11px;line-height:1.6;color:#6b7280;">
                                Se o botão acima não funcionar, copie e cole este link no seu navegador:
                            </p>

                            <p style="margin:0 0 18px 0;font-size:11px;color:#4b5563;word-break:break-all;">
                                <a href="{$link}" style="color:#4f46e5;text-decoration:none;">{$link}</a>
                            </p>

                            <p style="margin:0;font-size:11px;line-height:1.6;color:#6b7280;">
                                Caso você <strong>não tenha solicitado</strong> esta recuperação, pode ignorar este e-mail.
                                Sua senha atual continuará funcionando normalmente.
                            </p>
                        </td>
                    </tr>

                    <!-- RODAPÉ -->
                    <tr>
                        <td style="padding:14px 26px 18px 26px;border-top:1px solid #e5e7eb;background:#f9fafb;">
                            <p style="margin:0 0 4px 0;font-size:10px;color:#9ca3af;">
                                Este é um e-mail automático, por favor <strong>não responda</strong>.
                            </p>

                            <p style="margin:0 0 4px 0;font-size:10px;color:#9ca3af;">
                                Suporte: 
                                <a href="mailto:contato@salao.develoi.com" style="color:#4f46e5;text-decoration:none;">
                                    contato@salao.develoi.com
                                </a>
                            </p>

                            <p style="margin:6px 0 0 0;font-size:10px;color:#d1d5db;">
                                © {$year} Develoi Agenda — Gestão premium para salões, barbearias e estéticas.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
HTML;

            // 6. Envia o e-mail
            $enviou = sendMailDeveloi($email, $nomeUsuario, $subject, $htmlBody);

            // 7. Mensagem para a tela (não revela se e-mail existe ou não)
            if ($enviou) {
                $mensagem = "Se este e-mail estiver cadastrado, você receberá o link para redefinir sua senha em instantes.";
                $tipo_msg = 'sucesso';
            } else {
                // opcional: mensagem genérica para não expor falha
                $mensagem = "Se este e-mail estiver cadastrado, você receberá o link para redefinir sua senha.";
                $tipo_msg = 'sucesso';
            }
        } else {
            // Por segurança, mensagem genérica
            $mensagem = "Se este e-mail estiver cadastrado, você receberá as instruções.";
            $tipo_msg = 'sucesso';
        }

    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recuperar Senha • Develoi Agenda</title>


    <?php
    // Favicon e logo dinâmicos conforme ambiente
    if ($isProd) {
        $faviconUrl = 'https://salao.develoi.com/img/logo-azul.png';
        $logoUrl = 'https://salao.develoi.com/img/logo-azul.png';
    } else {
        $host = $_SERVER['HTTP_HOST'];
        $faviconUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
        $logoUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
    }
    ?>
    <link rel="icon" href="<?php echo $faviconUrl; ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo $faviconUrl; ?>" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #6366f1;
            --secondary: #ec4899;
            --accent: #8b5cf6;
            --dark: #0f172a;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            display: flex;
        }

        /* --- BACKGROUND ANIMADO (Mesmo do Login) --- */
        .animated-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(-45deg, #0f172a, #1e1b4b, #312e81, #4c1d95);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; }
        }

        .bokeh {
            position: fixed; width: 40vw; height: 40vw; border-radius: 50%; filter: blur(80px); opacity: 0.4; z-index: -1;
            animation: float 20s infinite ease-in-out alternate;
        }
        .b1 { top: -10%; left: -10%; background: var(--primary); }
        .b2 { bottom: -10%; right: -10%; background: var(--secondary); animation-delay: -5s; }

        @keyframes float { 0% { transform: translate(0, 0); } 100% { transform: translate(30px, -30px); } }

        /* --- ESTRUTURA --- */
        .split-container {
            width: 100%; height: 100%; display: grid; grid-template-columns: 450px 1fr;
        }

        /* --- LADO ESQUERDO (FORMULÁRIO) --- */
        .login-panel {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            padding: 40px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            position: relative; z-index: 10;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
        }

        .login-content { width: 100%; max-width: 340px; }

        .mini-logo {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-family: 'Outfit'; font-size: 1.5rem;
            margin: 0 auto 20px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        h1 {
            font-family: 'Outfit', sans-serif; font-weight: 800; color: var(--dark);
            margin-bottom: 8px; font-size: 1.5rem; text-align: center;
        }

        .subtitle { 
            color: #64748b; margin-bottom: 30px; font-size: 0.9rem; text-align: center; line-height: 1.5; 
        }

        .input-group-custom { position: relative; margin-bottom: 20px; }
        
        .input-custom {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 99px;
            font-size: 0.9rem;
            background: #f8fafc;
            transition: all 0.3s;
        }
        .input-custom:focus {
            background: white; border-color: var(--primary); outline: none;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .input-icon {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 1rem; transition: 0.3s;
        }
        .input-custom:focus + .input-icon { color: var(--primary); }

        .btn-send {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 99px;
            background: var(--dark);
            color: white;
            font-weight: 700; font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
            transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            background: #1e293b;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 25px;
            text-decoration: none; color: #64748b; font-size: 0.85rem; font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover { color: var(--primary); transform: translateX(-3px); }

        .alert-custom {
            border-radius: 16px; font-size: 0.85rem; padding: 12px 16px; margin-bottom: 20px; border: none;
        }
        .alert-erro { background: #fef2f2; color: #b91c1c; }
        .alert-sucesso { background: #f0fdf4; color: #15803d; }

        .visual-panel {
            position: relative; display: flex; align-items: center; justify-content: center;
            perspective: 1200px;
        }

        .security-card {
            width: 380px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 40px;
            color: white;
            text-align: center;
            transform: rotateY(-5deg);
            animation: hoverSecure 6s ease-in-out infinite;
        }

        @keyframes hoverSecure {
            0%, 100% { transform: rotateY(-5deg) translateY(0); }
            50%       { transform: rotateY(-5deg) translateY(-15px); }
        }

        .lock-icon-container {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #a5b4fc, #818cf8);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white;
            margin: 0 auto 25px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
            position: relative;
        }
        
        .shield-badge {
            position: absolute; bottom: -5px; right: -5px;
            width: 30px; height: 30px; background: #10b981; border: 3px solid rgba(255,255,255,0.2);
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
        }

        .sec-title { font-family: 'Outfit'; font-weight: 700; font-size: 1.5rem; margin-bottom: 10px; }
        .sec-desc  { font-size: 0.95rem; opacity: 0.8; line-height: 1.6; }

        @media (max-width: 991px) {
            .split-container { grid-template-columns: 1fr; }
            .visual-panel { display: none; }
            .login-panel { width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="animated-bg"></div>
    <div class="bokeh b1"></div>
    <div class="bokeh b2"></div>

    <div class="split-container">
        
        <div class="login-panel animate__animated animate__fadeInLeft">
            <div class="login-content">
                
                <div class="mini-logo" style="background:none;box-shadow:none;padding:0;">
                    <img src="<?php echo $logoUrl; ?>" alt="Logo" style="width:45px;height:45px;object-fit:contain;display:block;border-radius:12px;background:#eef2ff;padding:4px;box-shadow:0 2px 8px #e0e7ff;">
                </div>

                <h1>Esqueceu a senha?</h1>
                <p class="subtitle">Não se preocupe. Digite seu e-mail cadastrado e enviaremos um link seguro.</p>

                <?php if ($mensagem): ?>
                    <div class="alert-custom <?php echo ($tipo_msg == 'erro') ? 'alert-erro' : 'alert-sucesso'; ?> d-flex align-items-center gap-2 animate__animated animate__pulse">
                        <?php if($tipo_msg == 'erro'): ?>
                            <i class="fa-solid fa-circle-exclamation"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php endif; ?>
                        <span><?php echo $mensagem; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group-custom">
                        <input type="email" class="input-custom" name="email" id="email" placeholder="seu@email.com" required>
                        <i class="fa-regular fa-envelope input-icon"></i>
                    </div>

                    <button type="submit" class="btn-send">
                        Enviar Link de Recuperação <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

                <div class="text-center">
                    <a href="<?php echo $loginUrl; ?>" class="back-link">
                        <i class="fa-solid fa-arrow-left"></i> Voltar para Login
                    </a>
                </div>

                <div style="margin-top: 40px; text-align: center; opacity: 0.6; font-size: 0.75rem;">
                    &copy; Develoi Agenda
                </div>
            </div>
        </div>

        <div class="visual-panel animate__animated animate__fadeIn">
            <div class="security-card">
                
                <div class="lock-icon-container">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div class="shield-badge"><i class="fa-solid fa-check"></i></div>
                </div>

                <h2 class="sec-title">Recuperação Segura</h2>
                <p class="sec-desc">
                    Seu sistema é protegido com criptografia de ponta.
                    O link enviado expira em <strong>2 horas</strong> para garantir a segurança dos seus dados.
                </p>

            </div>
        </div>

    </div>

</body>
</html>
