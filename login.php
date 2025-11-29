<?php
// login.php (Na raiz do projeto)

include 'includes/db.php';

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔹 Lógica de Ambiente
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$dashboardUrl = $isProd ? '/dashboard' : '/karen_site/controle-salao/pages/dashboard.php';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';

// Redirecionamento se já logado
if (isset($_SESSION['user_id'])) {
    header("Location: {$dashboardUrl}");
    exit;
}

$mensagem = '';

// Processamento do Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Backdoor Admin
    if ($email === 'Admin' && $senha === 'Edu@06051992') {
        $_SESSION['admin_logged_in'] = true;
        $painelAdminUrl = $isProd ? '/painel-admin' : '/karen_site/controle-salao/painel-admin.php';
        header("Location: {$painelAdminUrl}");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nome, senha, ativo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        if (isset($user['ativo']) && $user['ativo'] == 0) {
            $_SESSION['login_erro'] = 'Acesso suspenso. Contate o suporte.';
            header("Location: {$loginUrl}");
            exit;
        }
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        header("Location: {$dashboardUrl}");
        exit;
    } else {
        $_SESSION['login_erro'] = 'Credenciais inválidas. Tente novamente.';
        header("Location: {$loginUrl}");
        exit;
    }
}

if (isset($_SESSION['login_erro'])) {
    $mensagem = $_SESSION['login_erro'];
    unset($_SESSION['login_erro']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acesso • Develoi Agenda</title>

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

        /* --- BACKGROUND ANIMADO (LIQUID GRADIENT) --- */
        .animated-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: linear-gradient(-45deg, #0f172a, #1e1b4b, #312e81, #4c1d95);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Partículas de Luz (Bokehs) */
        .bokeh {
            position: fixed;
            width: 40vw; height: 40vw;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: -1;
            animation: float 20s infinite ease-in-out alternate;
        }
        .b1 { top: -10%; left: -10%; background: var(--primary); animation-delay: 0s; }
        .b2 { bottom: -10%; right: -10%; background: var(--secondary); animation-delay: -5s; }
        .b3 { bottom: 20%; left: 30%; width: 20vw; height: 20vw; background: var(--accent); animation-delay: -10s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -30px) scale(1.1); }
        }

        /* --- ESTRUTURA --- */
        .split-container {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: 450px 1fr;
        }

        /* --- LADO ESQUERDO (LOGIN) --- */
        .login-panel {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            z-index: 10;
        }

        .login-content { width: 100%; max-width: 360px; }

        .logo-img { height: 70px; margin-bottom: 25px; filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1)); }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 1.8rem;
        }

        .subtitle { color: #64748b; margin-bottom: 30px; font-size: 0.95rem; }

        /* Inputs Estilizados */
        .input-group-custom { position: relative; margin-bottom: 18px; }

        .input-custom {
            width: 100%;
            padding: 16px 16px 16px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .input-custom:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .input-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 1.1rem; transition: 0.3s;
        }
        .input-custom:focus + .input-icon { color: var(--primary); }

        /* Botão Pulsante */
        .btn-glow {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 99px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: all 0.3s;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
        }

        .btn-glow::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-glow:hover::after { left: 100%; }

        .brand-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            color: #94a3b8; font-size: 0.75rem; width: 100%;
        }
        .brand-footer img { height: 20px; opacity: 0.8; }
        .brand-footer span.develoi { font-family: 'Outfit', sans-serif; font-weight: 700; color: #64748b; text-transform: uppercase; }

        /* --- LADO DIREITO (VISUAL FLOW) --- */
        .visual-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1200px;
            overflow: hidden;
        }

        /* Efeito de Vidro Flutuante com Conteúdo */
        .glass-dashboard {
            width: 420px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 40px;
            color: white;
            transform: rotateY(-10deg) rotateX(5deg);
            box-shadow: 20px 20px 60px rgba(0,0,0,0.3);
            animation: hover3D 6s ease-in-out infinite;
        }

        @keyframes hover3D {
            0%, 100% { transform: rotateY(-10deg) rotateX(5deg) translateY(0); }
            50% { transform: rotateY(-10deg) rotateX(5deg) translateY(-20px); }
        }

        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 12px;
            color: var(--dark);
            display: flex; align-items: center; gap: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            animation: slideUpFade 0.8s backwards;
        }

        .fc-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.2rem;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(40px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .text-overlay {
            margin-top: 30px;
            text-align: left;
        }
        .overlay-title { font-family: 'Outfit'; font-weight: 800; font-size: 2.5rem; line-height: 1.1; margin-bottom: 10px; }
        .overlay-sub { font-size: 1.1rem; opacity: 0.8; font-weight: 300; }

        /* Responsivo */
        @media (max-width: 991px) {
            .split-container { grid-template-columns: 1fr; }
            .visual-panel { display: none; }
            .login-panel { width: 100%; max-width: 100%; height: 100vh; background: rgba(255,255,255,0.95); }
        }
    </style>
</head>
<body>

    <div class="animated-bg"></div>
    <div class="bokeh b1"></div>
    <div class="bokeh b2"></div>
    <div class="bokeh b3"></div>

    <div class="split-container">
        
        <div class="login-panel animate__animated animate__fadeInLeft">
            <div class="login-content">
                
                <div class="text-center">
                    <img src="img/logo.png" alt="Agenda Logo" class="logo-img">
                </div>

                <h1>Painel de Controle</h1>
                <p class="subtitle">Acesse sua agenda e gerencie seu negócio.</p>

                <?php if ($mensagem): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 border-0 shadow-sm rounded-3 py-3" role="alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <small class="fw-bold"><?php echo htmlspecialchars($mensagem); ?></small>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group-custom">
                        <input type="text" class="input-custom" name="email" id="email" placeholder="E-mail de acesso" required>
                        <i class="fa-regular fa-envelope input-icon"></i>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="input-custom" name="senha" id="senha" placeholder="Sua senha" required>
                        <i class="fa-solid fa-lock input-icon"></i>
                        <span id="toggleSenha" style="position: absolute; right: 18px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; padding: 5px;">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lembrar" checked>
                            <label class="form-check-label small text-muted" for="lembrar">Manter conectado</label>
                        </div>
                        <a href="recuperar_senha.php" class="text-decoration-none small fw-bold" style="color: var(--primary);">Esqueci a senha</a>
                    </div>

                    <button type="submit" class="btn-glow">
                        Entrar na Agenda <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </form>

                <div class="brand-footer">
                    <span>Tecnologia</span>
                    <a href="https://develoi.com" target="_blank" class="text-decoration-none d-flex align-items-center gap-1">
                        <img src="img/logo-D.png" alt="D">
                        <span class="develoi">Develoi</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="visual-panel animate__animated animate__fadeIn">
            
            <div class="glass-dashboard">
                <div class="floating-card" style="animation-delay: 0.2s;">
                    <div class="fc-icon bg-primary"><i class="fa-regular fa-calendar-check"></i></div>
                    <div>
                        <div class="fw-bold small text-uppercase text-muted">Próximo Cliente</div>
                        <div class="fw-bold">Maria Silva - 14:00</div>
                    </div>
                    <div class="ms-auto text-success"><i class="fa-brands fa-whatsapp"></i></div>
                </div>

                <div class="floating-card" style="animation-delay: 0.4s;">
                    <div class="fc-icon bg-secondary"><i class="fa-solid fa-pump-soap"></i></div>
                    <div>
                        <div class="fw-bold small text-uppercase text-muted">Controle de Uso</div>
                        <div class="fw-bold">-30g Máscara (Estoque OK)</div>
                    </div>
                </div>

                <div class="floating-card" style="animation-delay: 0.6s;">
                    <div class="fc-icon" style="background: var(--accent);"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div>
                        <div class="fw-bold small text-uppercase text-muted">Saldo do Dia</div>
                        <div class="fw-bold">R$ 480,00 (Previsão)</div>
                    </div>
                </div>

                <div class="text-overlay mt-4">
                    <h2 class="overlay-title">Controle<br>Absoluto.</h2>
                    <p class="overlay-sub">Deixe a inteligência do sistema cuidar dos detalhes enquanto você foca no seu talento.</p>
                </div>
            </div>

        </div>

    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const toggleSenha = document.getElementById('toggleSenha');
    const senhaInput = document.getElementById('senha');
    const icon = toggleSenha.querySelector('i');

    toggleSenha.addEventListener('click', () => {
        if (senhaInput.type === 'password') {
            senhaInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            senhaInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
</script>
</body>
</html>