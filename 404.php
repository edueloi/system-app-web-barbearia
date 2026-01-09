<?php
// Define o código de status HTTP 404
http_response_code(404);

// Título da página
$pageTitle = 'Página Não Encontrada - 404';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/png" href="img/logo-azul.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --accent: #8b5cf6;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow: hidden;
        }

        /* Efeito de círculos animados no fundo */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        body::before {
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        body::after {
            width: 300px;
            height: 300px;
            bottom: -50px;
            left: -50px;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            33% {
                transform: translate(50px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-50px, 50px) scale(0.9);
            }
        }

        .error-container {
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
            padding: 80px 50px;
            text-align: center;
            max-width: 650px;
            width: 100%;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 1;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .error-icon {
            font-size: 140px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            animation: bounce 2.5s infinite;
            display: inline-block;
            filter: drop-shadow(0 10px 20px rgba(102, 126, 234, 0.3));
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-25px);
            }
        }

        .error-code {
            font-size: 120px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            line-height: 1;
            letter-spacing: -5px;
            text-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .error-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .error-message {
            font-size: 17px;
            color: var(--text-gray);
            margin-bottom: 50px;
            line-height: 1.7;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .button-group {
            display: flex;
            gap: 18px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 36px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .btn:hover::before {
            transform: translateX(100%);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--white);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.45);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.25);
        }

        .btn i {
            font-size: 18px;
        }

        /* Decoração adicional */
        .decoration-circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: pulse 3s infinite ease-in-out;
        }

        .circle-1 {
            width: 100px;
            height: 100px;
            background: var(--primary);
            top: 20px;
            right: 30px;
            animation-delay: 0s;
        }

        .circle-2 {
            width: 70px;
            height: 70px;
            background: var(--accent);
            bottom: 30px;
            left: 40px;
            animation-delay: 1s;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.2;
            }
        }

        @media (max-width: 600px) {
            .error-container {
                padding: 60px 30px;
                border-radius: 20px;
            }

            .error-code {
                font-size: 90px;
                letter-spacing: -3px;
            }

            .error-title {
                font-size: 26px;
            }

            .error-icon {
                font-size: 100px;
            }

            .error-message {
                font-size: 15px;
                margin-bottom: 40px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            body::before,
            body::after {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="decoration-circle circle-1"></div>
        <div class="decoration-circle circle-2"></div>
        
        <div class="error-icon">
            <i class="bi bi-emoji-frown"></i>
        </div>
        
        <div class="error-code">404</div>
        
        <h1 class="error-title">Página Não Encontrada</h1>
        
        <p class="error-message">
            Ops! A página que você está procurando não existe ou foi movida. 
            Não se preocupe, você pode voltar ou ir para a página inicial.
        </p>
        
        <div class="button-group">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Voltar
            </a>
            <a href="<?php echo isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com' ? '/login' : '/karen_site/controle-salao/login.php'; ?>" class="btn btn-primary">
                <i class="bi bi-house-door"></i>
                Página Inicial
            </a>
        </div>
    </div>
</body>
</html>
