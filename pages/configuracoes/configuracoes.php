<?php
$pageTitle = 'Configurações';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Proteção de Sessão
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

$msg = '';
$msgType = '';

// --- 1. ALTERAR SENHA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'nova_senha') {
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarSenha = $_POST['confirmar_senha'];

    // Buscar senha atual no banco
    $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        // Verifica se a senha atual bate (ou se é a primeira vez e está vazia/padrão)
        if (password_verify($senhaAtual, $user['senha'])) {
            if ($novaSenha === $confirmarSenha) {
                if (strlen($novaSenha) >= 6) {
                    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $userId]);
                    $msg = 'Senha atualizada com sucesso!';
                    $msgType = 'success';
                } else {
                    $msg = 'A nova senha deve ter pelo menos 6 caracteres.';
                    $msgType = 'error';
                }
            } else {
                $msg = 'A confirmação de senha não coincide.';
                $msgType = 'error';
            }
        } else {
            $msg = 'Senha atual incorreta.';
            $msgType = 'error';
        }
    }
}

// --- 2. BACKUP DO BANCO ---
if (isset($_GET['download_backup'])) {
    $file = '../../banco_salao.sqlite';
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup_salao_'.date('Y-m-d').'.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Detectar URL base para o Link de Agendamento
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Remove 'pages/configuracoes' e aponta para 'agendar.php' na raiz
$path = str_replace('/pages/configuracoes', '', dirname($_SERVER['PHP_SELF'])); 
$linkAgendamento = $protocol . "://" . $host . $path . "/../../agendar.php";
// Limpeza da URL para ficar bonita
$linkAgendamento = str_replace('/controls/../../', '/', $linkAgendamento); // Ajuste fino dependendo da pasta
// Método simples para garantir link da raiz:
$baseUrl = $protocol . "://" . $host . str_replace('pages/configuracoes/configuracoes.php', '', $_SERVER['SCRIPT_NAME']);
$linkFinal = $baseUrl . "agendar.php";

?>

<style>
    .config-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media(min-width: 768px) { .config-grid { grid-template-columns: 1fr 1fr; } }

    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: var(--shadow); border: 1px solid #f1f5f9; }
    .card-title { margin-top: 0; display: flex; align-items: center; gap: 10px; color: var(--text-dark); font-size: 1.2rem; }
    .card-desc { color: var(--text-gray); font-size: 0.9rem; margin-bottom: 20px; }

    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; }
    
    .btn-primary { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; width: 100%; }
    .btn-primary:hover { background: var(--primary-hover); }

    .btn-download { background: #0f172a; color: white; text-decoration: none; padding: 12px 20px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 600; transition: 0.2s; }
    .btn-download:hover { background: #334155; }

    .copy-box { display: flex; gap: 10px; }
    .btn-copy { background: #e0e7ff; color: var(--primary); border: none; padding: 0 20px; border-radius: 10px; cursor: pointer; font-weight: 600; }
    .btn-copy:hover { background: #c7d2fe; }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 0.95rem; }
    .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<main class="main-content">
    
    <div style="margin-bottom: 30px;">
        <h2 style="margin:0;">Configurações</h2>
        <p style="color:var(--text-gray); margin-top:5px;">Gerencie a segurança e ferramentas do sistema.</p>
    </div>

    <?php if($msg): ?>
        <div class="alert <?php echo $msgType; ?>">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="config-grid">
        
        <div class="card">
            <h3 class="card-title"><i class="bi bi-share-fill" style="color:#6366f1;"></i> Link para Clientes</h3>
            <p class="card-desc">Envie este link para os seus clientes agendarem sozinhos.</p>
            
            <label class="form-label">Link Público</label>
            <div class="copy-box">
                <input type="text" class="form-control" value="<?php echo $linkFinal; ?>" id="linkInput" readonly>
                <button class="btn-copy" onclick="copiarLink()">Copiar</button>
            </div>
            <p style="font-size:0.8rem; color:#94a3b8; margin-top:10px;">
                <i class="bi bi-whatsapp"></i> Dica: Cole este link na bio do Instagram ou envie no WhatsApp.
            </p>
        </div>

        <div class="card">
            <h3 class="card-title"><i class="bi bi-shield-lock-fill" style="color:#10b981;"></i> Alterar Senha</h3>
            <p class="card-desc">Atualize a sua senha de acesso ao painel.</p>

            <form method="POST">
                <input type="hidden" name="acao" value="nova_senha">
                
                <div class="form-group">
                    <label class="form-label">Senha Atual</label>
                    <input type="password" name="senha_atual" class="form-control" required placeholder="Digite sua senha atual">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="nova_senha" class="form-control" required placeholder="Mínimo 6 caracteres">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <input type="password" name="confirmar_senha" class="form-control" required placeholder="Repita a nova senha">
                </div>

                <button type="submit" class="btn-primary">Atualizar Senha</button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-title"><i class="bi bi-database-down" style="color:#f59e0b;"></i> Backup de Dados</h3>
            <p class="card-desc">Baixe uma cópia de segurança de todos os seus dados (clientes, agenda, financeiro).</p>
            
            <div style="background:#fff7ed; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ffedd5; font-size:0.9rem; color:#9a3412;">
                Recomendamos fazer o download semanalmente para garantir que nunca perde as suas informações.
            </div>

            <a href="?download_backup=1" class="btn-download">
                <i class="bi bi-download"></i> Baixar Banco de Dados
            </a>
        </div>

    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
    function copiarLink() {
        var copyText = document.getElementById("linkInput");
        copyText.select();
        copyText.setSelectionRange(0, 99999); // Para mobile
        navigator.clipboard.writeText(copyText.value);
        
        // Feedback visual simples
        const btn = document.querySelector('.btn-copy');
        const originalText = btn.innerText;
        btn.innerText = 'Copiado!';
        btn.style.background = '#dcfce7';
        btn.style.color = '#166534';
        
        setTimeout(() => {
            btn.innerText = originalText;
            btn.style.background = '#e0e7ff';
            btn.style.color = 'var(--primary)';
        }, 2000);
    }
</script>