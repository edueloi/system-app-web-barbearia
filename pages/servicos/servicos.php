<?php
require_once __DIR__ . '/../../includes/config.php';
// --- 1. LÓGICA PHP (CRIAR, EDITAR, EXCLUIR) ---
include '../../includes/db.php';

// Simulação de User ID

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProd ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// 🔹 URL para voltar para a tela de serviços
// 👉 AJUSTE essa URL local conforme seu path no XAMPP/WAMP
$servicosUrl = $isProd
    ? '/servicos' // em produção usa rota amigável
    : '/karen_site/controle-salao/pages/servicos/servicos.php';

// Cria pasta uploads se não existir
if (!is_dir('../../uploads')) { mkdir('../../uploads', 0777, true); }

// A. EXCLUIR
if (isset($_GET['delete'])) {
    $idDelete = $_GET['delete'];
    
    // Buscar foto antes de deletar para remover do servidor
    $stmt = $pdo->prepare("SELECT foto FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);
    $servico = $stmt->fetch();
    
    // Deletar registro do banco
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);
    
    // Deletar arquivo de foto se existir
    if (!empty($servico['foto'])) {
        $caminhoFoto = __DIR__ . '/../../' . $servico['foto'];
        if (file_exists($caminhoFoto)) {
            @unlink($caminhoFoto);
        }
    }
    
    echo "<script>window.location.href='{$servicosUrl}';</script>";
    exit;
}

// B. SALVAR (CRIAR OU EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao   = $_POST['acao']; // 'create' ou 'update'
    $idEdit = $_POST['id_servico'] ?? null;

    $nome    = $_POST['nome'];
    $preco   = str_replace(',', '.', $_POST['preco']);
    $duracao = $_POST['duracao'];
    $obs     = $_POST['obs'];
    $tipo    = $_POST['tipo'];
    $calculoServicoId = !empty($_POST['calculo_servico_id']) ? (int)$_POST['calculo_servico_id'] : null;
    
    // Novos campos de pacote
    $qtdSessoes = isset($_POST['qtd_sessoes']) ? (int)$_POST['qtd_sessoes'] : 1;
    $descontoTipo = $_POST['desconto_tipo'] ?? 'percentual';
    $descontoValor = isset($_POST['desconto_valor']) ? str_replace(',', '.', $_POST['desconto_valor']) : 0;
    $precoOriginal = isset($_POST['preco_original']) ? str_replace(',', '.', $_POST['preco_original']) : 0;

    $itens = isset($_POST['itens_selecionados']) ? implode(',', $_POST['itens_selecionados']) : '';

    // Caminho FÍSICO da pasta uploads (no servidor)
    $uploadDirFs = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDirFs)) { mkdir($uploadDirFs, 0777, true); }

    // Caminho salvo no banco (relativo ao projeto)
    $uploadDirDb = 'uploads/';

    $stmtCalcs = $pdo->prepare("
        SELECT id, nome_servico, valor_cobrado
        FROM calculo_servico
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmtCalcs->execute([$userId]);
    $calculos = $stmtCalcs->fetchAll();


    // Upload de Imagem
    $fotoPath = $_POST['foto_atual'] ?? '';
    if (!empty($_FILES['foto']['name'])) {
        if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext        = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $permitidas)) {
                // Deletar foto antiga se existir
                if (!empty($fotoPath)) {
                    $fotoAntigaPath = __DIR__ . '/../../' . $fotoPath;
                    if (file_exists($fotoAntigaPath)) {
                        @unlink($fotoAntigaPath);
                    }
                }
                
                $novoNome  = uniqid('srv_', true) . '.' . $ext;
                $destinoFs = $uploadDirFs . $novoNome;
                $caminhoDb = $uploadDirDb . $novoNome;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $destinoFs)) {
                    $fotoPath = $caminhoDb;
                } else {
                    error_log('Falha ao mover o upload de foto para: ' . $destinoFs);
                }
            } else {
                error_log('Extensão de imagem não permitida: ' . $ext);
            }
        } else {
            error_log('Erro no upload da foto: código ' . $_FILES['foto']['error']);
        }
    }

    if (!empty($nome) && !empty($preco)) {
        if ($acao === 'update' && $idEdit) {
            // ATUALIZAR
            $sql = "UPDATE servicos 
                       SET nome=?, preco=?, duracao=?, foto=?, observacao=?, itens_pacote=?, calculo_servico_id=?,
                           qtd_sessoes=?, desconto_tipo=?, desconto_valor=?, preco_original=?
                     WHERE id=? AND user_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $preco, $duracao, $fotoPath, $obs, $itens, $calculoServicoId, 
                           $qtdSessoes, $descontoTipo, $descontoValor, $precoOriginal, $idEdit, $userId]);
        } else {
            // CRIAR NOVO
            $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote, calculo_servico_id,
                                         qtd_sessoes, desconto_tipo, desconto_valor, preco_original)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens, $calculoServicoId,
                           $qtdSessoes, $descontoTipo, $descontoValor, $precoOriginal]);
        }

        header("Location: {$servicosUrl}?status=saved");
        exit;
    }
}

$pageTitle = 'Meus Serviços';
include '../../includes/header.php';
include '../../includes/menu.php';

// --- 2. BUSCAR DADOS ---
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$todosRegistros = $stmt->fetchAll();

$listaServicos = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'unico'; });
$listaPacotes  = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'pacote'; });
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    :root {
        --primary: #6366f1;
        --primary-soft: #eef2ff;
        --primary-hover: #4f46e5;
        --primary-rgb: 99, 102, 241;
        --danger: #ef4444;
        --danger-soft: #fee2e2;
        --success: #10b981;
        --success-soft: #d1fae5;
        --warning: #f59e0b;
        --warning-soft: #fef3c7;
        --bg-page: #f8fafc;
        --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --border-soft: #e2e8f0;
        --shadow-soft: 0 10px 24px rgba(15,23,42,0.08);
        --shadow-card: 0 4px 12px rgba(15,23,42,0.06);
        --shadow-strong: 0 20px 40px rgba(99,102,241,0.25);
        --radius-xl: 28px;
        --radius-lg: 20px;
        --radius-md: 16px;
        --radius-sm: 12px;
        --radius-pill: 999px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 14px;
        background: var(--bg-page);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Container principal da página */
    .main-content {
        padding: 20px 16px 100px 16px;
        max-width: 1200px;
        margin: 0 auto;
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (min-width: 768px) {
        .main-content {
            padding: 24px 24px 100px 24px;
        }
    }

    /* Header da página */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
    }
    
    .page-title-wrap {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .page-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .page-subtitle {
        margin: 0;
        font-size: 0.85rem;
        color: var(--text-gray);
        font-weight: 500;
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    @media (max-width: 480px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .page-header-actions {
            width: 100%;
            justify-content: stretch;
        }
        .page-header-actions .btn-chip {
            flex: 1;
            justify-content: center;
        }
    }

    /* Botão "Novo" compacto */
    .btn-chip {
        border-radius: var(--radius-pill);
        border: none;
        padding: 10px 18px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        cursor: pointer;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        color: #fff;
        box-shadow: var(--shadow-strong);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        letter-spacing: 0.01em;
    }
    .btn-chip i { 
        font-size: 1rem;
        transition: transform 0.2s;
    }
    .btn-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 24px 48px rgba(99,102,241,0.3);
    }
    .btn-chip:hover i {
        transform: scale(1.1);
    }
    .btn-chip:active {
        transform: translateY(0) scale(0.98);
    }

    /* Barra de Pesquisa */
    .search-bar-container {
        position: relative;
        margin-bottom: 20px;
    }
    .search-input {
        width: 100%;
        padding: 14px 16px 14px 48px;
        border-radius: var(--radius-pill);
        border: 2px solid transparent;
        font-size: 0.9rem;
        background: #ffffff;
        box-shadow: var(--shadow-card);
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.2s;
    }
    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1), var(--shadow-card);
    }
    .search-input::placeholder {
        color: #94a3b8;
        font-weight: 400;
    }
    .search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-gray);
        font-size: 1.1rem;
        pointer-events: none;
    }

    /* Tabs estilo “segment control” */
    .tabs-wrapper {
        margin-bottom: 18px;
    }
    .tabs-pill {
        background: #e5e7eb;
        padding: 4px;
        border-radius: var(--radius-pill);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .tab-btn {
        padding: 7px 16px;
        border-radius: 999px;
        border: none;
        background: transparent;
        font-size: 0.8rem;
        color: #4b5563;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.15s;
        white-space: nowrap;
    }
    .tab-btn i {
        font-size: 1rem;
        transition: transform 0.2s;
    }
    .tab-btn.active {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.35);
    }
    .tab-btn.active i {
        transform: scale(1.1);
    }
    .tab-btn:hover:not(.active) {
        background: var(--primary-soft);
        color: var(--primary);
    }

    /* Grid de cartões */
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        animation: fadeIn 0.5s ease-out;
    }
    @media (max-width: 900px) {
        .services-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
    }
    @media (max-width: 768px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
    }
    @media (max-width: 480px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
    }

    /* Card de serviço */
    .service-card {
        background: #ffffff;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-card);
        border: 1px solid rgba(148,163,184,0.08);
        display: flex;
        flex-direction: column;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 220px;
        cursor: pointer;
    }
    .service-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 20px 40px rgba(99,102,241,0.15);
        border-color: rgba(var(--primary-rgb), 0.2);
    }
    .service-card:active {
        transform: translateY(-2px) scale(0.99);
    }

    /* Badge de tipo (Pacote) */
    .service-chip {
        position: absolute;
        left: 10px;
        top: 10px;
        font-size: 0.7rem;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #ffffff;
        padding: 4px 10px;
        border-radius: var(--radius-pill);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(251,191,36,0.4);
        letter-spacing: 0.02em;
    }

    /* Botões de Ação no Card (Editar/Excluir) */
    .card-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 6px;
        z-index: 10;
        transition: all 0.2s;
    }
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        background: rgba(255,255,255,0.98);
        backdrop-filter: blur(8px);
        border: none;
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dark);
        font-size: 0.9rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .action-btn:hover {
        transform: scale(1.1);
        background: #ffffff;
        box-shadow: 0 6px 16px rgba(15,23,42,0.25);
    }
    .action-btn:active {
        transform: scale(0.95);
    }
    .btn-edit { 
        color: var(--primary);
    }
    .btn-edit:hover {
        background: var(--primary);
        color: white;
    }
    .btn-delete { 
        color: var(--danger);
    }
    .btn-delete:hover {
        background: var(--danger);
        color: white;
    }

    /* Imagem do Card */
    .service-img {
        height: 120px;
        background-color: #f1f5f9;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cbd5e1;
    }

    /* Conteúdo do Card */
    .service-body {
        padding: 9px 11px 10px 11px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .service-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 2px;
        font-size: 0.88rem;
        line-height: 1.25;
    }
    .service-meta {
        font-size: 0.76rem;
        color: var(--text-gray);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .service-meta i {
        font-size: 0.9rem;
    }
    .service-price {
        color: var(--primary);
        font-weight: 800;
        font-size: 1rem;
        margin-top: auto;
    }

    /* Botão principal reutilizável */
    .btn-submit {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        color: white;
        width: 100%;
        padding: 16px 24px;
        border: none;
        border-radius: var(--radius-pill);
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: var(--shadow-strong);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: 0.01em;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        margin-top: 8px;
    }
    .btn-submit i { 
        font-size: 1.2rem;
        transition: transform 0.2s;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 28px 56px rgba(99,102,241,0.35);
    }
    .btn-submit:hover i {
        transform: scale(1.1);
    }
    .btn-submit:active {
        transform: translateY(0) scale(0.98);
    }

    /* Modal estilo app */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.6);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        animation: fadeIn 0.25s ease-out;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-box {
        background: #ffffff;
        padding: 24px;
        border-radius: 28px;
        width: 96%;
        max-width: 520px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px rgba(15,23,42,0.35);
        transform: scale(0.95) translateY(20px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        animation: modalSlideUp 0.3s ease-out forwards;
    }
    
    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @media (max-width: 768px) {
        .modal-overlay {
            align-items: flex-end;
        }
        .modal-box {
            width: 100%;
            max-width: 100%;
            border-radius: 28px 28px 0 0;
            margin: 0;
            max-height: 92vh;
            padding: 20px 20px 32px 20px;
            animation: modalSlideUpMobile 0.3s ease-out forwards;
        }
        @keyframes modalSlideUpMobile {
            from {
                opacity: 0;
                transform: translateY(100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }

    /* Formulário do modal */
    .form-group {
        margin-bottom: 18px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .form-label i {
        color: var(--primary);
        font-size: 1rem;
    }
    .form-control {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid transparent;
        border-radius: var(--radius-md);
        box-sizing: border-box;
        font-size: 0.9rem;
        background: #f8fafc;
        font-weight: 500;
        transition: all 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1);
    }
    .form-control::placeholder {
        color: #94a3b8;
        font-weight: 400;
    }
    textarea.form-control {
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
    }
    select.form-control {
        cursor: pointer;
    }

    /* Lista de serviços dentro do pacote */
    .checkbox-list {
        max-height: 280px;
        overflow-y: auto;
        border: 2px solid var(--border-soft);
        border-radius: var(--radius-md);
        padding: 12px;
        background: #ffffff;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    }
    .checkbox-list::-webkit-scrollbar {
        width: 6px;
    }
    .checkbox-list::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 999px;
    }
    .checkbox-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }
    .checkbox-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .check-item {
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        gap: 12px;
    }
    .check-item:last-child {
        border-bottom: none;
    }
    .check-item label {
        cursor: pointer;
        user-select: none;
        font-weight: 500;
    }
    .check-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary);
    }
    .check-item span {
        white-space: nowrap;
        color: var(--primary);
        font-weight: 700;
        font-size: 0.85rem;
    }

    .modal-header-bar {
        width: 48px;
        height: 5px;
        background: #cbd5e1;
        border-radius: 999px;
        margin: -8px auto 20px auto;
        display: none;
    }
    
    @media (max-width: 768px) {
        .modal-header-bar {
            display: block;
        }
    }
    
    .modal-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .modal-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .modal-close-btn {
        background: #f1f5f9;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 999px;
        cursor: pointer;
        color: #64748b;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .modal-close-btn:hover {
        background: #e2e8f0;
        transform: rotate(90deg);
    }
    .modal-close-btn:active {
        transform: rotate(90deg) scale(0.95);
    }

    .dual-input-row {
        display:flex;
        gap:10px;
    }
    @media (max-width: 480px) {
        .dual-input-row {
            flex-direction:row;
        }
    }

    /* Modal de Confirmação */
    .confirm-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.7);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
        animation: fadeIn 0.2s ease-out;
    }
    .confirm-modal.active {
        display: flex;
    }
    .confirm-box {
        background: #ffffff;
        padding: 28px;
        border-radius: 24px;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 25px 50px rgba(15,23,42,0.4);
        text-align: center;
        animation: modalSlideUp 0.25s ease-out;
    }
    .confirm-icon {
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 2rem;
        color: #dc2626;
    }
    .confirm-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0 0 8px 0;
    }
    .confirm-text {
        font-size: 0.9rem;
        color: var(--text-gray);
        margin: 0 0 24px 0;
        line-height: 1.5;
    }
    .confirm-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    .btn-confirm-cancel,
    .btn-confirm-delete {
        padding: 12px 24px;
        border-radius: var(--radius-pill);
        border: none;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-confirm-cancel {
        background: #f1f5f9;
        color: #64748b;
        flex: 1;
    }
    .btn-confirm-cancel:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    .btn-confirm-delete {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        flex: 1;
        box-shadow: 0 10px 20px rgba(239,68,68,0.3);
    }
    .btn-confirm-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(239,68,68,0.4);
    }
    .btn-confirm-cancel:active,
    .btn-confirm-delete:active {
        transform: scale(0.98);
    }

    /* Botões do modal - responsivo */
    .modal-buttons {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }
    .btn-cancel {
        flex: 1;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        padding: 14px 20px;
        border-radius: var(--radius-pill);
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .btn-cancel:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    .btn-cancel:active {
        transform: scale(0.98);
    }
    .btn-submit {
        flex: 2;
    }
    @media (max-width: 480px) {
        .modal-buttons {
            flex-direction: column-reverse;
        }
        .btn-cancel,
        .btn-submit {
            flex: 1;
            width: 100%;
        }
    }
</style>

<main class="main-content">

    <div class="page-header">
        <div class="page-title-wrap">
            <h1 class="page-title">Meus Serviços</h1>
            <p class="page-subtitle">Organize serviços e pacotes que seus clientes podem agendar.</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-chip" onclick="abrirModal('unico')" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow:0 20px 40px rgba(16,185,129,0.25);">
                <i class="bi bi-plus-lg"></i>
                Serviço
            </button>
            <button class="btn-chip" onclick="abrirModal('pacote')">
                <i class="bi bi-box-seam"></i>
                Pacote
            </button>
        </div>
    </div>

    <div class="search-bar-container">
        <i class="bi bi-search search-icon"></i>
        <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Buscar por nome do serviço ou pacote..."
            onkeyup="filtrarServicos()"
        >
    </div>

    <div class="tabs-wrapper">
        <div class="tabs-pill">
            <button class="tab-btn active" onclick="showTab('servicos', this)">
                <i class="bi bi-scissors"></i> Serviços
            </button>
            <button class="tab-btn" onclick="showTab('pacotes', this)">
                <i class="bi bi-box-seam"></i> Pacotes
            </button>
        </div>
    </div>

    <!-- LISTA DE SERVIÇOS -->
    <div id="tab-servicos" class="tab-content">
        <div class="services-grid">
            <?php foreach ($listaServicos as $s): ?>
                <div class="service-card" data-nome="<?php echo strtolower($s['nome']); ?>">
                    <div class="card-actions">
                        <button class="action-btn btn-edit" onclick='editar(<?php echo json_encode($s); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="action-btn btn-delete" onclick="confirmarDelete(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['nome'])); ?>')">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>

                    <div class="service-img" style="<?php echo $s['foto'] ? "background-image: url('../../{$s['foto']}')" : ""; ?>">
                        <?php if (!$s['foto']): ?>
                            <i class="bi bi-scissors" style="font-size: 2.2rem;"></i>
                        <?php endif; ?>
                    </div>

                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($s['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i>
                            <?php echo $s['duracao']; ?> min
                        </div>
                        <div class="service-price">
                            R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LISTA DE PACOTES -->
    <div id="tab-pacotes" class="tab-content" style="display: none;">
        <div class="services-grid">
            <?php foreach ($listaPacotes as $p): ?>
                <div class="service-card" data-nome="<?php echo strtolower($p['nome']); ?>">
                    <span class="service-chip">
                        <i class="bi bi-stars"></i> Pacote
                    </span>

                    <div class="card-actions">
                        <button class="action-btn btn-edit" onclick='editar(<?php echo json_encode($p); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="action-btn btn-delete" onclick="confirmarDelete(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['nome'])); ?>')">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>

                    <div class="service-img" style="<?php echo $p['foto'] ? "background-image: url('../../{$p['foto']}')" : ""; ?>">
                        <?php if (!$p['foto']): ?>
                            <i class="bi bi-box-seam" style="font-size: 2.2rem;"></i>
                        <?php endif; ?>
                    </div>

                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($p['nome']); ?></div>
                        
                        <?php if (!empty($p['qtd_sessoes']) && $p['qtd_sessoes'] > 1): ?>
                            <div style="background:#f0f9ff; padding:6px 10px; border-radius:8px; margin:8px 0; border:1px solid #bae6fd;">
                                <small style="color:#0369a1; font-weight:600; font-size:0.75rem;">
                                    <i class="bi bi-repeat"></i> <?php echo $p['qtd_sessoes']; ?>x sessões
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="service-meta">
                            <i class="bi bi-clock"></i>
                            <?php echo $p['duracao']; ?> min
                        </div>
                        
                        <?php if (!empty($p['desconto_valor']) && $p['desconto_valor'] > 0): ?>
                            <div style="margin-top:8px;">
                                <small style="text-decoration:line-through; color:#94a3b8; font-size:0.8rem;">
                                    R$ <?php echo number_format($p['preco_original'], 2, ',', '.'); ?>
                                </small>
                                <span style="background:#fef2f2; color:#dc2626; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:600; margin-left:6px;">
                                    <?php 
                                    if ($p['desconto_tipo'] == 'percentual') {
                                        echo number_format($p['desconto_valor'], 0) . '% OFF';
                                    } else {
                                        echo '-R$ ' . number_format($p['desconto_valor'], 2, ',', '.');
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="service-price" style="color:#16a34a; font-weight:700;">
                            R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<!-- MODAL -->
<div class="modal-overlay" id="modalServico">
    <div class="modal-box">
        <div class="modal-header-bar"></div>

        <div class="modal-header-row">
            <h3 id="modalTitle" class="modal-title">Novo Serviço</h3>
            <button type="button" class="modal-close-btn" onclick="fecharModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" id="inputAcao" value="create">
            <input type="hidden" name="id_servico" id="inputIdServico" value="">
            <input type="hidden" name="tipo" id="inputTipo" value="unico">
            <input type="hidden" name="foto_atual" id="inputFotoAtual" value="">

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-tag-fill"></i>
                    Nome do serviço / pacote
                </label>
                <input type="text" name="nome" id="inputNome" class="form-control" placeholder="Ex: Corte Degrade + Barba" required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-calculator-fill"></i>
                    Cálculo de custos vinculado (opcional)
                </label>
                <select name="calculo_servico_id" class="form-control">
                    <option value="">Nenhum (preencher manual)</option>
                    <?php foreach ($calculos as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                            <?php if (!empty($servicoEdicao['calculo_servico_id']) && $servicoEdicao['calculo_servico_id'] == $c['id']) echo 'selected'; ?>
                        >
                            <?php echo htmlspecialchars($c['nome_servico']); ?> - R$ <?php echo number_format($c['valor_cobrado'], 2, ',', '.'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="areaSelecaoItens" style="display:none;">
                <label class="form-label">
                    <i class="bi bi-box-seam-fill"></i>
                    Itens do pacote
                </label>
                <div class="checkbox-list">
                    <?php if(count($listaServicos) > 0): ?>
                        <?php foreach($listaServicos as $s): ?>
                            <div class="check-item" style="flex-direction:column; align-items:flex-start; gap:8px;">
                                <div style="display:flex; align-items:center; gap:8px; width:100%;">
                                    <label style="display:flex; align-items:center; gap:8px; flex:1;">
                                        <input type="checkbox"
                                               name="itens_selecionados[]"
                                               value="<?php echo $s['id']; ?>"
                                               class="chk-servico"
                                               data-price="<?php echo $s['preco']; ?>"
                                               data-time="<?php echo $s['duracao']; ?>"
                                               data-service-id="<?php echo $s['id']; ?>"
                                               onchange="toggleQuantidadeServico(this)">
                                        <?php echo htmlspecialchars($s['nome']); ?>
                                    </label>
                                    <span style="font-weight:700;">R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></span>
                                </div>
                                <div class="quantidade-servico-item" id="qtd-<?php echo $s['id']; ?>" style="display:none; width:100%; padding-left:28px;">
                                    <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:8px;">
                                        <label style="font-size:0.75rem; color:#64748b; font-weight:600; white-space:nowrap;">
                                            <i class="bi bi-repeat"></i> Quantidade:
                                        </label>
                                        <input type="number" 
                                               min="1" 
                                               value="1" 
                                               class="form-control qtd-servico-input" 
                                               name="qtd_servico_<?php echo $s['id']; ?>"
                                               data-service-id="<?php echo $s['id']; ?>"
                                               style="width:80px; padding:6px; text-align:center; font-weight:700;"
                                               oninput="calcularPacote()">
                                        <span style="font-size:0.75rem; color:#64748b;">sessões</span>
                                        <span class="total-servico" style="margin-left:auto; font-weight:700; color:var(--brand-color);">
                                            R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="font-size:0.78rem; color:var(--text-gray);">
                            Cadastre ao menos um serviço para montar pacotes.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Campos de Pacote: Desconto -->
            <div id="areaPacoteExtras" style="display:none;">
                <div class="dual-input-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">
                            <i class="bi bi-percent"></i>
                            Tipo de desconto
                        </label>
                        <select name="desconto_tipo" id="inputDescontoTipo" class="form-control" onchange="calcularDescontoPacote()">
                            <option value="percentual">Percentual (%)</option>
                            <option value="valor">Valor (R$)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">
                            <i class="bi bi-tag"></i>
                            Desconto
                        </label>
                        <input type="number" step="0.01" min="0" name="desconto_valor" id="inputDescontoValor" class="form-control" placeholder="0" value="0" oninput="calcularDescontoPacote()">
                    </div>
                </div>

                <div class="form-group">
                    <div style="background:#f0fdf4; border:1px solid #86efac; padding:12px; border-radius:12px; margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong style="font-size:0.85rem; color:#166534;">Preço Original:</strong>
                            <span id="displayPrecoOriginal" style="font-weight:700; color:#166534;">R$ 0,00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong style="font-size:0.85rem; color:#dc2626;">Desconto:</strong>
                            <span id="displayDesconto" style="font-weight:700; color:#dc2626;">- R$ 0,00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid #86efac;">
                            <strong style="font-size:0.95rem; color:#166534;">Valor Final:</strong>
                            <span id="displayPrecoFinal" style="font-size:1.1rem; font-weight:700; color:#166534;">R$ 0,00</span>
                        </div>
                    </div>
                    <input type="hidden" name="preco_original" id="inputPrecoOriginal" value="0">
                </div>
            </div>

            <div class="dual-input-row">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">
                        <i class="bi bi-currency-dollar"></i>
                        Valor (R$)
                    </label>
                    <input type="number" step="0.01" name="preco" id="inputPreco" class="form-control" placeholder="0,00" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">
                        <i class="bi bi-clock-fill"></i>
                        Tempo total (min)
                    </label>
                    <input type="number" name="duracao" id="inputTempo" class="form-control" placeholder="30" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-image-fill"></i>
                    Foto de capa
                </label>
                <input type="file" name="foto" class="form-control" accept="image/*">
                <small style="color:#94a3b8; font-size:0.75rem; display:block; margin-top:6px;">
                    <i class="bi bi-info-circle"></i> Imagens horizontais ficam mais bonitas no card (JPG, PNG ou WEBP).
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-chat-left-text-fill"></i>
                    Descrição (opcional)
                </label>
                <textarea name="obs" id="inputObs" class="form-control" rows="3" placeholder="Detalhes, condições ou observações do serviço..."></textarea>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="fecharModal()">
                    <i class="bi bi-x-circle"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn-submit" id="btnSalvar">
                    <i class="bi bi-check-circle-fill"></i>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE CONFIRMAÇÃO DELETE -->
<div class="confirm-modal" id="confirmModal">
    <div class="confirm-box">
        <div class="confirm-icon">
            <i class="bi bi-trash3-fill"></i>
        </div>
        <h3 class="confirm-title">Excluir item?</h3>
        <p class="confirm-text" id="confirmText">Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.</p>
        <div class="confirm-actions">
            <button class="btn-confirm-cancel" onclick="fecharConfirmModal()">
                <i class="bi bi-x-circle"></i>
                Cancelar
            </button>
            <button class="btn-confirm-delete" id="btnConfirmDelete">
                <i class="bi bi-trash3-fill"></i>
                Excluir
            </button>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    const modal         = document.getElementById('modalServico');
    const inputAcao     = document.getElementById('inputAcao');
    const inputId       = document.getElementById('inputIdServico');
    const inputTipo     = document.getElementById('inputTipo');
    const inputNome     = document.getElementById('inputNome');
    const inputPreco    = document.getElementById('inputPreco');
    const inputTempo    = document.getElementById('inputTempo');
    const inputObs      = document.getElementById('inputObs');
    const inputFotoAtual= document.getElementById('inputFotoAtual');
    const modalTitle    = document.getElementById('modalTitle');
    const btnSalvar     = document.getElementById('btnSalvar');
    const areaItens     = document.getElementById('areaSelecaoItens');

    // 1. ABRIR MODAL (NOVO)
    function abrirModal(tipo) {
        inputAcao.value   = 'create';
        inputId.value     = '';
        inputFotoAtual.value = '';
        inputNome.value   = '';
        inputPreco.value  = '';
        inputTempo.value  = '';
        inputObs.value    = '';
        btnSalvar.innerHTML = '<i class="bi bi-check-circle-fill"></i> Salvar';
        document.querySelectorAll('.chk-servico').forEach(chk => chk.checked = false);
        
        // Resetar campos de pacote
        document.getElementById('inputDescontoTipo').value = 'percentual';
        document.getElementById('inputDescontoValor').value = 0;
        document.getElementById('inputPrecoOriginal').value = 0;
        
        // Resetar e ocultar todos os campos de quantidade individual
        document.querySelectorAll('.quantidade-servico-item').forEach(div => {
            div.style.display = 'none';
            const input = div.querySelector('.qtd-servico-input');
            if (input) input.value = 1;
        });

        inputTipo.value = tipo;
        const areaPacoteExtras = document.getElementById('areaPacoteExtras');
        
        if (tipo === 'pacote') {
            modalTitle.innerText = 'Novo Pacote';
            areaItens.style.display = 'block';
            areaPacoteExtras.style.display = 'block';
        } else {
            modalTitle.innerText = 'Novo Serviço';
            areaItens.style.display = 'none';
            areaPacoteExtras.style.display = 'none';
        }

        modal.classList.add('active');
    }

    // 2. ABRIR MODAL (EDITAR)
    function editar(dados) {
        inputAcao.value   = 'update';
        inputId.value     = dados.id;
        inputTipo.value   = dados.tipo;
        inputNome.value   = dados.nome;
        inputPreco.value  = dados.preco;
        inputTempo.value  = dados.duracao;
        inputObs.value    = dados.observacao || '';
        inputFotoAtual.value = dados.foto || '';
        btnSalvar.innerHTML = '<i class="bi bi-check-circle-fill"></i> Atualizar';
        
        // Carregar dados de desconto (pacote)
        document.getElementById('inputDescontoTipo').value = dados.desconto_tipo || 'percentual';
        document.getElementById('inputDescontoValor').value = dados.desconto_valor || 0;
        document.getElementById('inputPrecoOriginal').value = dados.preco_original || 0;

        const areaPacoteExtras = document.getElementById('areaPacoteExtras');
        
        if (dados.tipo === 'pacote') {
            modalTitle.innerText = 'Editar Pacote';
            areaItens.style.display = 'block';
            areaPacoteExtras.style.display = 'block';

            // Marcar checkboxes e mostrar quantidades
            let itens = dados.itens_pacote ? dados.itens_pacote.split(',') : [];
            document.querySelectorAll('.chk-servico').forEach(chk => {
                const isChecked = itens.includes(chk.value);
                chk.checked = isChecked;
                
                if (isChecked) {
                    // Mostrar campo de quantidade
                    const serviceId = chk.getAttribute('data-service-id');
                    const qtdDiv = document.getElementById('qtd-' + serviceId);
                    if (qtdDiv) {
                        qtdDiv.style.display = 'block';
                    }
                }
            });
            
            // Recalcular totais
            calcularPacote();
            calcularDescontoPacote();
        } else {
            modalTitle.innerText = 'Editar Serviço';
            areaItens.style.display = 'none';
            areaPacoteExtras.style.display = 'none';
        }

        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // 3. TOGGLE QUANTIDADE POR SERVIÇO
    function toggleQuantidadeServico(checkbox) {
        const serviceId = checkbox.getAttribute('data-service-id');
        const qtdDiv = document.getElementById('qtd-' + serviceId);
        
        if (checkbox.checked) {
            qtdDiv.style.display = 'block';
        } else {
            qtdDiv.style.display = 'none';
            // Reset quantidade quando desmarca
            const input = qtdDiv.querySelector('.qtd-servico-input');
            if (input) input.value = 1;
        }
        
        calcularPacote();
    }

    // 4. PESQUISA INSTANTÂNEA
    function filtrarServicos() {
        let termo = document.getElementById('searchInput').value.toLowerCase();
        let cards = document.querySelectorAll('.service-card');

        cards.forEach(card => {
            let nome = card.getAttribute('data-nome') || '';
            if (nome.includes(termo)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // 5. CÁLCULO AUTOMÁTICO DE PACOTE
    function calcularPacote() {
        if (inputTipo.value !== 'pacote') return;

        let totalPreco = 0;
        let totalTempo = 0;

        document.querySelectorAll('.chk-servico:checked').forEach(chk => {
            const serviceId = chk.getAttribute('data-service-id');
            const preco = parseFloat(chk.getAttribute('data-price')) || 0;
            const tempo = parseInt(chk.getAttribute('data-time')) || 0;
            
            // Buscar quantidade individual deste serviço
            const qtdInput = document.querySelector(`input[data-service-id="${serviceId}"].qtd-servico-input`);
            const quantidade = qtdInput ? parseInt(qtdInput.value) || 1 : 1;
            
            // Multiplicar preço e tempo pela quantidade individual
            totalPreco += preco * quantidade;
            totalTempo += tempo * quantidade;
            
            // Atualizar display do total deste serviço
            const totalSpan = document.querySelector(`#qtd-${serviceId} .total-servico`);
            if (totalSpan) {
                totalSpan.textContent = 'R$ ' + (preco * quantidade).toFixed(2).replace('.', ',');
            }
        });

        // Salvar preço original (já com todas as quantidades calculadas)
        document.getElementById('inputPrecoOriginal').value = totalPreco.toFixed(2);

        if (inputAcao.value === 'create') {
            inputTempo.value = totalTempo;
            // Aplicar desconto se houver
            calcularDescontoPacote();
        }
    }
    
    // 5. CALCULAR DESCONTO DO PACOTE
    function calcularDescontoPacote() {
        const precoOriginal = parseFloat(document.getElementById('inputPrecoOriginal').value) || 0;
        const descontoTipo = document.getElementById('inputDescontoTipo').value;
        const descontoValor = parseFloat(document.getElementById('inputDescontoValor').value) || 0;
        
        let valorDesconto = 0;
        let precoFinal = precoOriginal;
        
        if (descontoValor > 0) {
            if (descontoTipo === 'percentual') {
                // Desconto em %
                valorDesconto = (precoOriginal * descontoValor) / 100;
                precoFinal = precoOriginal - valorDesconto;
            } else {
                // Desconto em R$
                valorDesconto = descontoValor;
                precoFinal = precoOriginal - valorDesconto;
            }
        }
        
        // Não permitir preço final negativo
        if (precoFinal < 0) precoFinal = 0;
        
        // Atualizar displays
        document.getElementById('displayPrecoOriginal').innerText = 'R$ ' + precoOriginal.toFixed(2).replace('.', ',');
        document.getElementById('displayDesconto').innerText = '- R$ ' + valorDesconto.toFixed(2).replace('.', ',');
        document.getElementById('displayPrecoFinal').innerText = 'R$ ' + precoFinal.toFixed(2).replace('.', ',');
        
        // Atualizar campo de preço
        document.getElementById('inputPreco').value = precoFinal.toFixed(2);
    }
    
    // 6. MODAL DE CONFIRMAÇÃO DELETE
    const confirmModal = document.getElementById('confirmModal');
    const confirmText = document.getElementById('confirmText');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    let deleteId = null;

    function confirmarDelete(id, nome) {
        deleteId = id;
        confirmText.innerHTML = `Tem certeza que deseja excluir <strong>"${nome}"</strong>?<br><small style="color:#94a3b8; margin-top:8px; display:block;">A imagem também será removida permanentemente.</small>`;
        confirmModal.classList.add('active');
    }

    function fecharConfirmModal() {
        confirmModal.classList.remove('active');
        deleteId = null;
    }

    btnConfirmDelete.addEventListener('click', function() {
        if (deleteId) {
            window.location.href = '<?php echo $servicosUrl; ?>?delete=' + deleteId;
        }
    });

    // Fechar modal ao clicar fora
    confirmModal.addEventListener('click', function(e) {
        if (e.target === confirmModal) {
            fecharConfirmModal();
        }
    });

    // 7. TROCA DE ABAS
    function showTab(tabName, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        const target = document.getElementById('tab-' + tabName);
        if (target) target.style.display = 'block';
        if (btn) btn.classList.add('active');

        // Atualiza botão principal no topo
        const headerBtn = document.querySelector('.btn-chip');
        if (headerBtn) {
            if (tabName === 'pacotes') {
                headerBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Novo pacote';
                headerBtn.setAttribute('onclick', "abrirModal('pacote')");
            } else {
                headerBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Novo serviço';
                headerBtn.setAttribute('onclick', "abrirModal('unico')");
            }
        }
    }
</script>
