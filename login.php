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

    // Backdoor Admin (não interfere com sessões de usuários comuns)
    if ($email === 'Admin' && $senha === 'Edu@06051992') {
        // Limpa apenas dados de usuário comum se existirem
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        // Define sessão de admin
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['ADMIN_LAST_ACTIVITY'] = time();
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
        // Limpa qualquer flag de admin antes de logar usuário comum
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['ADMIN_LAST_ACTIVITY']);
        // Define sessão de usuário
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
            --primary: #0f2f66;
            --secondary: #2563eb;
            --accent: #1e3a8a;
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
            background: linear-gradient(-45deg, #0b1220, #0f2f66, #1e3a8a, #0b1220);
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
        .b1 { top: -10%; left: -10%; background: #1e3a8a; animation-delay: 0s; }
        .b2 { bottom: -10%; right: -10%; background: #0f2f66; animation-delay: -5s; }
        .b3 { bottom: 20%; left: 30%; width: 20vw; height: 20vw; background: #2563eb; animation-delay: -10s; }

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
            background: linear-gradient(180deg, rgba(248,250,252,0.98), rgba(241,245,249,0.95));
            backdrop-filter: blur(20px);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            z-index: 10;
            border-right: 1px solid rgba(148,163,184,0.2);
        }

        .login-content { width: 100%; max-width: 360px; }

        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .login-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #1e3a8a;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .logo-img { height: 64px; margin-bottom: 22px; filter: drop-shadow(0 6px 14px rgba(15,23,42,0.12)); }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 1.7rem;
        }

        .subtitle { color: #64748b; margin-bottom: 22px; font-size: 0.95rem; }

        /* Inputs Estilizados */
        .input-group-custom { position: relative; margin-bottom: 16px; }

        .input-custom {
            width: 100%;
            padding: 15px 16px 15px 45px;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(15,23,42,0.06);
        }

        .input-custom:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15,47,102,0.15);
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
            box-shadow: 0 4px 15px rgba(15,47,102,0.35);
            transition: all 0.3s;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.02em;
        }

        .btn-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15,47,102,0.4);
        }

        .btn-glow::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-glow:hover::after { left: 100%; }

        .brand-footer {
            margin-top: 32px;
            padding-top: 18px;
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

        .visual-shell {
            width: 520px;
            max-width: 90vw;
            padding: 36px;
            border-radius: 32px;
            background: rgba(15, 47, 102, 0.12);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 30px 80px rgba(2, 6, 23, 0.45);
            position: relative;
            overflow: hidden;
            transform: rotateY(-6deg) rotateX(4deg);
            animation: hover3D 8s ease-in-out infinite;
        }

        .visual-shell::before {
            content: "";
            position: absolute;
            inset: -30% -10% auto -10%;
            height: 60%;
            background: radial-gradient(circle at 30% 20%, rgba(37,99,235,0.35), transparent 60%);
            opacity: 0.7;
        }

        .visual-shell::after {
            content: "";
            position: absolute;
            right: -40%;
            bottom: -50%;
            width: 80%;
            height: 80%;
            background: radial-gradient(circle at 30% 20%, rgba(30,58,138,0.6), transparent 60%);
            opacity: 0.5;
        }

        .visual-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255,255,255,0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 36px 36px;
            opacity: 0.35;
            pointer-events: none;
        }

        @keyframes hover3D {
            0%, 100% { transform: rotateY(-6deg) rotateX(4deg) translateY(0); }
            50% { transform: rotateY(-6deg) rotateX(4deg) translateY(-18px); }
        }

        .visual-header {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 18px;
        }

        .visual-title {
            font-family: 'Outfit';
            font-weight: 800;
            font-size: 2.2rem;
            line-height: 1.1;
            color: #ffffff;
            margin: 0;
            text-shadow: 0 12px 30px rgba(2, 6, 23, 0.45);
        }

        .visual-sub {
            font-size: 1.02rem;
            color: rgba(255,255,255,0.78);
            margin: 0;
        }

        .visual-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            color: rgba(255,255,255,0.95);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            width: fit-content;
        }

        .visual-metrics {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .metric-card {
            padding: 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.94);
            color: var(--dark);
            box-shadow: 0 12px 24px rgba(2, 6, 23, 0.18);
            border: 1px solid rgba(226,232,240,0.8);
        }

        .metric-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            font-weight: 700;
        }

        .metric-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 6px;
        }

        .metric-badge {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e3a8a;
        }

        .visual-stack {
            position: relative;
            z-index: 2;
            display: grid;
            gap: 10px;
        }

        .stack-card {
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255,255,255,0.96);
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 12px 26px rgba(2, 6, 23, 0.16);
            animation: slideUpFade 0.8s backwards;
            border: 1px solid rgba(226,232,240,0.8);
        }

        .stack-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.15rem;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        .stack-meta {
            color: #64748b;
            font-size: 0.78rem;
        }

        .stack-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,0.15);
            margin-left: auto;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(40px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        #toggleSenha {
            border-radius: 999px;
            transition: 0.2s;
        }
        #toggleSenha:hover {
            color: var(--primary);
            background: rgba(15,47,102,0.08);
        }

        /* Responsivo */
        @media (max-width: 991px) {
            .split-container { grid-template-columns: 1fr; }
            .visual-panel { display: none; }
            .login-panel { width: 100%; max-width: 100%; height: 100vh; background: rgba(248,250,252,0.98); }
        }

        @media (max-width: 1200px) {
            .visual-shell {
                width: 460px;
                padding: 28px;
            }

            .visual-metrics {
                grid-template-columns: 1fr;
            }
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
                
                <div class="login-header">
                    <div class="login-tag"><i class="fa-solid fa-shield-halved"></i> acesso seguro</div>
                    <img src="img/logo.png" alt="Agenda Logo" class="logo-img">
                    <h1>Painel de Controle</h1>
                    <p class="subtitle">Acesse sua agenda e gerencie seu negócio.</p>
                </div>

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
            <div class="visual-shell">
                <div class="visual-grid"></div>
                <div class="visual-header">
                    <h2 class="visual-title">Agenda inteligente<br>para rotina cheia.</h2>
                    <p class="visual-sub">Tudo em um painel: horarios, estoque e faturamento no mesmo fluxo.</p>
                    <div class="visual-chip"><i class="fa-solid fa-bolt"></i> painel ao vivo</div>
                </div>

                <div class="visual-metrics">
                    <div class="metric-card">
                        <div class="metric-title">Agenda do dia</div>
                        <div class="metric-value">12 atendimentos</div>
                        <div class="metric-badge"><i class="fa-solid fa-circle-check"></i> 8 confirmados</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-title">Faturamento</div>
                        <div class="metric-value">R$ 1.240,00</div>
                        <div class="metric-badge"><i class="fa-solid fa-arrow-trend-up"></i> +18%</div>
                    </div>
                </div>

                <div class="visual-stack">
                    <div class="stack-card" style="animation-delay: 0.2s;">
                        <div class="stack-icon"><i class="fa-regular fa-calendar-check"></i></div>
                        <div>
                            <div class="fw-bold">Proximo cliente</div>
                            <div class="stack-meta">Maria Silva · 14:00</div>
                        </div>
                        <span class="stack-dot"></span>
                    </div>
                    <div class="stack-card" style="animation-delay: 0.4s;">
                        <div class="stack-icon"><i class="fa-solid fa-pump-soap"></i></div>
                        <div>
                            <div class="fw-bold">Controle de estoque</div>
                            <div class="stack-meta">Mascara 30g · nivel ok</div>
                        </div>
                    </div>
                    <div class="stack-card" style="animation-delay: 0.6s;">
                        <div class="stack-icon"><i class="fa-solid fa-message"></i></div>
                        <div>
                            <div class="fw-bold">Confirmação</div>
                            <div class="stack-meta">Lembretes enviados hoje</div>
                        </div>
                    </div>
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
