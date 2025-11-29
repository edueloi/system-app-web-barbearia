<?php
// recuperar_senha.php

include 'includes/db.php';

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
            // 2. Define validade (ex: 1 hora a partir de agora)
            $validade = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. Salva no banco
            $update = $pdo->prepare("UPDATE usuarios SET token_recuperacao = ?, token_validade = ? WHERE id = ?");
            $update->execute([$token, $validade, $user['id']]);

            // 4. Link de recuperação (Simulação de envio)
            // Na vida real, você usaria a função mail() ou PHPMailer aqui.
            $link = "http://" . $_SERVER['HTTP_HOST'] . "/nova_senha.php?token=" . $token;

            // Como não temos envio de e-mail configurado no exemplo, mostro uma mensagem de sucesso simulada
            $mensagem = "Enviamos um link de recuperação para <b>$email</b>. Verifique sua caixa de entrada (e spam).";
            $tipo_msg = 'sucesso';
            
            // Em produção, não mostre o link na tela, envie por email!
        } else {
            // Por segurança, não dizemos se o e-mail não existe, para evitar enumeração de usuários.
            // Ou dizemos algo genérico.
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

    <link rel="icon" href="favicon.ico" type="image/x-icon">

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

        /* Estilo da Logo Pequena */
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

        /* Input Arredondado e Delicado */
        .input-group-custom { position: relative; margin-bottom: 20px; }
        
        .input-custom {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 99px; /* BEM REDONDO */
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

        /* Botão Enviar */
        .btn-send {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 99px; /* BEM REDONDO */
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

        /* Link Voltar */
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 25px;
            text-decoration: none; color: #64748b; font-size: 0.85rem; font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover { color: var(--primary); transform: translateX(-3px); }

        /* Alertas */
        .alert-custom {
            border-radius: 16px; font-size: 0.85rem; padding: 12px 16px; margin-bottom: 20px; border: none;
        }
        .alert-erro { background: #fef2f2; color: #b91c1c; }
        .alert-sucesso { background: #f0fdf4; color: #15803d; }

        /* --- LADO DIREITO (VISUAL SEGURO) --- */
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
            50% { transform: rotateY(-5deg) translateY(-15px); }
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
        .sec-desc { font-size: 0.95rem; opacity: 0.8; line-height: 1.6; }

        /* Responsivo */
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
                
                <div class="mini-logo">D</div>

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
                    O link enviado expira em 1 hora para garantir a segurança dos seus dados.
                </p>

            </div>
        </div>

    </div>

</body>
</html>