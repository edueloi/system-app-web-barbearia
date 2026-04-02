<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProd ? '/login' : '../../login.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Busca dados atuais
$stmt = $pdo->prepare("SELECT nome, estabelecimento, tipo_estabelecimento, telefone, foto FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Se já tem estabelecimento, não precisa de onboarding
if (!empty($user['estabelecimento'])) {
    $dashUrl = $isProd ? '/dashboard' : '../../pages/dashboard.php';
    header('Location: ' . $dashUrl);
    exit;
}

$erro   = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = trim($_POST['nome'] ?? '');
    $estabelecimento = trim($_POST['estabelecimento'] ?? '');
    $tipo            = $_POST['tipo_estabelecimento'] ?? 'Salão de Beleza';
    $telefone        = trim($_POST['telefone'] ?? '') ?: null;

    if (!$nome || !$estabelecimento) {
        $erro = 'Preencha seu nome e o nome do estabelecimento.';
    } else {
        // Upload de foto
        $fotoPath = $user['foto'] ?? null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $extsOk = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $extsOk) && $_FILES['foto']['size'] < 3 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../../uploads/perfil/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = 'u' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fileName)) {
                    $fotoPath = 'uploads/perfil/' . $fileName;
                }
            }
        }

        $upd = $pdo->prepare("UPDATE usuarios SET nome=?, estabelecimento=?, tipo_estabelecimento=?, telefone=?, foto=? WHERE id=?");
        $upd->execute([$nome, $estabelecimento, $tipo, $telefone, $fotoPath, $userId]);

        $_SESSION['user_name'] = $nome;
        $sucesso = true;
        $dashUrl = $isProd ? '/dashboard' : '../../pages/dashboard.php';
        header('Location: ' . $dashUrl);
        exit;
    }
}

$nomeAtual = $user['nome'] ?? '';
$tiposEstab = ['Salão de Beleza','Barbearia','Nail Art','Estética','Spa','Studio','Outro'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo — Configure seu perfil</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 24px;
            padding: 40px 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            border-radius: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #fff; margin-bottom: 12px;
        }
        h1 { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 0.88rem; color: #64748b; margin-bottom: 28px; }

        /* Avatar upload */
        .avatar-wrap {
            display: flex; justify-content: center; margin-bottom: 24px;
        }
        .avatar-label {
            position: relative; cursor: pointer;
        }
        .avatar-preview {
            width: 88px; height: 88px; border-radius: 50%;
            background: #e0e7ff;
            border: 3px solid #c7d2fe;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #4f46e5;
            overflow: hidden;
        }
        .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-edit {
            position: absolute; bottom: 0; right: 0;
            width: 28px; height: 28px; border-radius: 50%;
            background: #1e3a8a; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; border: 2px solid #fff;
        }
        #inputFoto { display: none; }

        /* Form */
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        input[type=text], input[type=tel], select {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            font-size: 0.9rem; color: #0f172a;
            outline: none; transition: border-color .18s;
            background: #f8fafc;
        }
        input:focus, select:focus { border-color: #1e3a8a; background: #fff; }
        .erro { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; border-radius: 10px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 16px; }
        .btn {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            color: #fff; border: none; border-radius: 14px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            margin-top: 8px; transition: opacity .18s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn:hover { opacity: 0.9; }
        .step-hint {
            text-align: center; font-size: 0.75rem; color: #94a3b8; margin-top: 16px;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon"><i class="bi bi-scissors"></i></div>
        <h1>Bem-vindo! 👋</h1>
        <p class="subtitle">Configure seu perfil para começar a usar o sistema</p>
    </div>

    <?php if ($erro): ?>
        <div class="erro"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- Avatar -->
        <div class="avatar-wrap">
            <label class="avatar-label" for="inputFoto">
                <div class="avatar-preview" id="avatarPreview">
                    <?php if (!empty($user['foto'])): ?>
                        <img src="../../<?= htmlspecialchars($user['foto']) ?>" id="avatarImg" alt="foto">
                    <?php else: ?>
                        <i class="bi bi-person-fill" id="avatarIcon"></i>
                    <?php endif; ?>
                </div>
                <div class="avatar-edit"><i class="bi bi-camera-fill"></i></div>
            </label>
            <input type="file" name="foto" id="inputFoto" accept="image/*">
        </div>

        <div class="form-group">
            <label>Seu nome completo *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($nomeAtual) ?>" placeholder="Ex: Ana Paula Silva" required>
        </div>

        <div class="form-group">
            <label>Nome do estabelecimento *</label>
            <input type="text" name="estabelecimento" placeholder="Ex: Studio Ana Paula" required>
        </div>

        <div class="form-group">
            <label>Tipo de estabelecimento</label>
            <select name="tipo_estabelecimento">
                <?php foreach ($tiposEstab as $t): ?>
                    <option value="<?= $t ?>" <?= ($user['tipo_estabelecimento'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>WhatsApp / Telefone</label>
            <input type="tel" name="telefone" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" placeholder="(11) 99999-9999">
        </div>

        <button type="submit" class="btn">
            <i class="bi bi-check-circle-fill"></i>
            Começar a usar
        </button>

        <p class="step-hint">Você pode completar o restante do perfil depois</p>
    </form>
</div>

<script>
    document.getElementById('inputFoto').addEventListener('change', function(){
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e){
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(file);
    });
</script>
</body>
</html>
