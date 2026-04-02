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
    
    // Campos de pacote — modelo simplificado: serviço base + qtd sessões + desconto
    $qtdSessoes    = isset($_POST['qtd_sessoes']) ? max(1, (int)$_POST['qtd_sessoes']) : 1;
    $descontoTipo  = $_POST['desconto_tipo'] ?? 'percentual';
    $descontoValor = isset($_POST['desconto_valor']) ? str_replace(',', '.', $_POST['desconto_valor']) : 0;
    $precoOriginal = isset($_POST['preco_original']) ? str_replace(',', '.', $_POST['preco_original']) : 0;
    // servico_base_id armazenado em itens_pacote (um único ID)
    $itens = !empty($_POST['servico_base_id']) ? (string)(int)$_POST['servico_base_id'] : '';

    // Recorrência desativada no pacote — gerenciada no agendamento/comanda
    $permiteRecorrencia = 0;
    $tipoRecorrencia    = 'sem_recorrencia';
    $intervaloDias      = 1;
    $duracaoMeses       = 1;
    $qtdOcorrencias     = $qtdSessoes;
    $diasSemana         = null;
    $diaFixoMes         = null;

    // Caminho FÍSICO da pasta uploads (no servidor)
    $uploadDirFs = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDirFs)) { mkdir($uploadDirFs, 0777, true); }

    // Caminho salvo no banco (relativo ao projeto)
    $uploadDirDb = 'uploads/';

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
                           qtd_sessoes=?, desconto_tipo=?, desconto_valor=?, preco_original=?,
                           permite_recorrencia=?, tipo_recorrencia=?, intervalo_dias=?, duracao_meses=?,
                           qtd_ocorrencias=?, dias_semana=?, dia_fixo_mes=?
                     WHERE id=? AND user_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $preco, $duracao, $fotoPath, $obs, $itens, $calculoServicoId, 
                           $qtdSessoes, $descontoTipo, $descontoValor, $precoOriginal,
                           $permiteRecorrencia, $tipoRecorrencia, $intervaloDias, $duracaoMeses,
                           $qtdOcorrencias, $diasSemana, $diaFixoMes, $idEdit, $userId]);
        } else {
            // CRIAR NOVO
            $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote, calculo_servico_id,
                                         qtd_sessoes, desconto_tipo, desconto_valor, preco_original,
                                         permite_recorrencia, tipo_recorrencia, intervalo_dias, duracao_meses,
                                         qtd_ocorrencias, dias_semana, dia_fixo_mes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens, $calculoServicoId,
                           $qtdSessoes, $descontoTipo, $descontoValor, $precoOriginal,
                           $permiteRecorrencia, $tipoRecorrencia, $intervaloDias, $duracaoMeses,
                           $qtdOcorrencias, $diasSemana, $diaFixoMes]);
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

// Buscar cálculos de custos para o dropdown no modal
$stmtCalcs = $pdo->prepare("
    SELECT id, nome_servico, valor_cobrado
    FROM calculo_servico
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmtCalcs->execute([$userId]);
$calculos = $stmtCalcs->fetchAll();
?>

<style>
    /* === ESTILO PADRÃO DO PAINEL === */
    /* Fonte pequena delicada, clean, moderno, bordas arredondadas */
    /* Fundo neutro, cards brancos, 100% responsivo */
    
    :root {
        --primary-color: #0f2f66;
        --primary-dark: #1e3a8a;
        --primary-light: #dbeafe;
        
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        
        --text-main: #0f172a;
        --text-muted: #64748b;
        
        --border: #e2e8f0;
        --border-soft: #f1f5f9;
        
        --danger: #ef4444;
        --success: #16a34a;
        --warning: #f59e0b;
        
        --radius-sm: 10px;
        --radius-md: 14px;
        --radius-lg: 18px;
        
        --shadow-sm: 0 1px 2px rgba(15,23,42,0.06);
        --shadow-card: 0 6px 14px rgba(15,23,42,0.08);
        --shadow-hover: 0 12px 28px rgba(15,23,42,0.12);
        --shadow-strong: 0 18px 40px rgba(15,23,42,0.2);
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        font-size: 0.875rem;
        background: var(--bg-page);
        color: var(--text-main);
        line-height: 1.5;
    }

    .main-content {
        padding: 1.25rem 1rem 6rem;
        max-width: 75rem;
        margin: 0 auto;
    }

    @media (min-width: 768px) {
        .main-content {
            padding: 1.5rem 1.5rem 6rem;
        }
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }
    
    .page-title-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .page-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-main);
    }
    
    .page-subtitle {
        margin: 0;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .page-header-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .page-header-actions {
            width: 100%;
        }
        .page-header-actions .btn-chip {
            flex: 1;
            justify-content: center;
        }
    }

    .btn-chip {
        border-radius: 999px;
        border: none;
        padding: 0.625rem 1rem;
        font-size: 0.8125rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        cursor: pointer;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: #fff;
        box-shadow: var(--shadow-card);
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .btn-chip i { 
        font-size: 0.875rem;
    }
    .btn-chip:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-hover);
    }
    .btn-chip:active {
        transform: translateY(0);
    }

    .search-bar-container {
        position: relative;
        margin-bottom: 1rem;
    }
    .search-input {
        width: 100%;
        padding: 0.75rem 0.875rem 0.75rem 2.5rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        font-size: 0.8125rem;
        background: var(--bg-card);
        box-shadow: var(--shadow-sm);
        color: var(--text-main);
        transition: all 0.2s;
    }
    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15,47,102,0.15);
    }
    .search-input::placeholder {
        color: var(--text-muted);
    }
    .search-icon {
        position: absolute;
        left: 0.875rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.875rem;
        pointer-events: none;
    }

    /* Tabs estilo “segment control” */
    .tabs-wrapper {
        margin-bottom: 18px;
    }
    .tabs-pill {
        background: #ffffff;
        padding: 4px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(148,163,184,0.25);
        box-shadow: var(--shadow-sm);
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
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: #ffffff;
        box-shadow: var(--shadow-card);
    }
    .tab-btn:hover:not(.active) {
        background: rgba(15,47,102,0.08);
        color: var(--primary-color);
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(12.5rem, 1fr));
        gap: 0.875rem;
    }
    @media (max-width: 768px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
    }

    .service-card {
        background: var(--bg-card);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: var(--shadow-card);
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: relative;
        transition: all 0.2s ease;
        min-height: 13.75rem;
        cursor: pointer;
    }
    
    /* Cards de pacotes precisam de mais espaço para mostrar os itens */
    .service-card[data-tipo="pacote"] {
        min-height: auto;
    }
    
    .service-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        border-color: rgba(15,47,102,0.2);
    }

    .service-chip {
        position: absolute;
        left: 0.625rem;
        top: 0.625rem;
        font-size: 0.625rem;
        background: var(--warning);
        color: #ffffff;
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 600;
        box-shadow: var(--shadow-sm);
    }

    .card-actions {
        position: absolute;
        top: 0.625rem;
        right: 0.625rem;
        display: flex;
        gap: 0.375rem;
        z-index: 10;
    }
    .action-btn {
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 10px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
        font-size: 0.75rem;
        transition: all 0.2s ease;
    }
    .action-btn:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-card);
    }
    .btn-edit { 
        color: var(--primary-color);
    }
    .btn-edit:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .btn-delete { 
        color: var(--danger);
    }
    .btn-delete:hover {
        background: var(--danger);
        color: white;
        border-color: var(--danger);
    }

    .service-img {
        height: 7.5rem;
        background: linear-gradient(135deg, #eff6ff, #f8fafc);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cbd5e1;
    }

    .service-body {
        padding: 0.625rem 0.75rem 0.75rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .service-title {
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 0.25rem;
        font-size: 0.8125rem;
        line-height: 1.3;
    }
    .service-meta {
        font-size: 0.6875rem;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .service-meta i {
        font-size: 0.75rem;
    }
    .service-price {
        color: var(--primary-color);
        font-weight: 700;
        font-size: 0.9375rem;
        margin-top: auto;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        width: 100%;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.8125rem;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        margin-top: 0.5rem;
    }
    .btn-submit i { 
        font-size: 0.9375rem;
    }
    .btn-submit:hover {
        box-shadow: var(--shadow-card);
    }
    .btn-submit:active {
        transform: scale(0.98);
    }

    .modal-box form {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .recorrencia-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .weekday-options {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .weekday-chip {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }

    .weekday-chip input {
        display: none;
    }

    .weekday-chip span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        padding: 7px 12px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--text-muted);
        transition: all 0.15s ease;
    }

    .weekday-chip input:checked + span {
        background: #dbeafe;
        color: #1e3a8a;
        border-color: #bfdbfe;
        box-shadow: 0 6px 14px rgba(15,47,102,0.15);
    }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-box {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        width: 96%;
        max-width: 32.5rem;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-strong);
        animation: modalSlideUp 0.2s ease-out forwards;
        border: 1px solid rgba(148,163,184,0.25);
        position: relative;
    }

    .modal-box::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 64px;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        background: linear-gradient(135deg, rgba(15,47,102,0.12), rgba(37,99,235,0.08));
        pointer-events: none;
    }

    .modal-box > * {
        position: relative;
        z-index: 1;
    }
    
    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(1.25rem);
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
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            margin: 0;
            max-height: 92vh;
            padding: 1.25rem 1.25rem 2rem;
        }
    }

.form-group {
        margin-bottom: 1rem;
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 0.75rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.375rem;
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    .form-label i {
        color: var(--primary-color);
        font-size: 0.875rem;
    }
.form-control {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        box-sizing: border-box;
        font-size: 0.8125rem;
        background: #f8fafc;
        transition: all 0.2s ease;
    }
.form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(15,47,102,0.12);
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
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        background: #f8fafc;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    }
    .checkbox-list::-webkit-scrollbar {
        width: 0.375rem;
    }
    .checkbox-list::-webkit-scrollbar-track {
        background: var(--bg-page);
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
        padding: 0.625rem 0.25rem;
        border-bottom: 1px solid var(--border-soft);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        gap: 0.75rem;
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
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: var(--primary-color);
    }
    .check-item span {
        white-space: nowrap;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 0.75rem;
    }

    .modal-header-bar {
        width: 3rem;
        height: 0.3125rem;
        background: #cbd5e1;
        border-radius: 999px;
        margin: -0.5rem auto 1.25rem;
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
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }
    .modal-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-main);
    }
    .modal-close-btn {
        background: var(--bg-page);
        border: none;
        width: 1.875rem;
        height: 1.875rem;
        border-radius: 50%;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .modal-close-btn:hover {
        background: var(--border);
        color: var(--text-main);
    }
    .modal-close-btn:active {
        transform: scale(0.95);
    }

    .dual-input-row {
        display: flex;
        gap: 0.625rem;
    }
    @media (max-width: 768px) {
        .dual-input-row {
            flex-direction: column;
        }
    }

    .confirm-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
    }
    .confirm-modal.active {
        display: flex;
    }
    .confirm-box {
        background: #f8fafc;
        padding: 1.75rem;
        border-radius: var(--radius-lg);
        width: 90%;
        max-width: 25rem;
        box-shadow: var(--shadow-strong);
        text-align: center;
        border: 1px solid rgba(148,163,184,0.25);
    }
    .confirm-icon {
        width: 3rem;
        height: 3rem;
        background: #fee2e2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.125rem;
        color: var(--danger);
    }
    .confirm-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 0.5rem 0;
    }
    .confirm-text {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin: 0 0 1.5rem 0;
        line-height: 1.4;
    }
    .confirm-actions {
        display: flex;
        gap: 0.625rem;
        justify-content: center;
    }
    .btn-confirm-cancel,
    .btn-confirm-delete {
        padding: 0.625rem 1.25rem;
        border-radius: 999px;
        border: none;
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    .btn-confirm-cancel {
        background: #e2e8f0;
        color: var(--text-muted);
        flex: 1;
        border: 1px solid var(--border);
    }
    .btn-confirm-cancel:hover {
        background: #dbeafe;
        color: var(--text-main);
    }
    .btn-confirm-delete {
        background: var(--danger);
        color: white;
        flex: 1;
        box-shadow: var(--shadow-sm);
    }
    .btn-confirm-delete:hover {
        background: #dc2626;
        box-shadow: var(--shadow-card);
    }
    .btn-confirm-cancel:active,
    .btn-confirm-delete:active {
        transform: scale(0.98);
    }

    .modal-buttons {
        display: flex;
        gap: 0.625rem;
        margin-top: 0.5rem;
    }
    .btn-cancel {
        flex: 1;
        background: #e2e8f0;
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 0.75rem 1.25rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.8125rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
    }
    .btn-cancel:hover {
        background: #dbeafe;
        color: var(--text-main);
    }
    .btn-cancel:active {
        transform: scale(0.98);
    }
    .btn-submit {
        flex: 2;
    }
    @media (max-width: 768px) {
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
            <button class="btn-chip" onclick="abrirModal('unico')" style="background: #10b981;">
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
                <div class="service-card" data-nome="<?php echo strtolower($p['nome']); ?>" data-tipo="pacote">
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
                        
                        <!-- ITENS DO PACOTE -->
                        <?php if (!empty($p['itens_pacote'])): ?>
                            <div style="background:#f8fafc; padding:8px 10px; border-radius:10px; margin:8px 0; border:1px solid #e2e8f0;">
                                <div style="font-size:0.65rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:6px; display:flex; align-items:center; gap:4px;">
                                    <i class="bi bi-check2-circle"></i> Inclui:
                                </div>
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    <?php
                                    $itensIds = explode(',', $p['itens_pacote']);
                                    foreach ($itensIds as $itemId) {
                                        if (empty($itemId)) continue;
                                        $stmtItem = $pdo->prepare("SELECT nome FROM servicos WHERE id = ? AND user_id = ?");
                                        $stmtItem->execute([$itemId, $userId]);
                                        $item = $stmtItem->fetch();
                                        if ($item):
                                    ?>
                                        <div style="font-size:0.7rem; color:#475569; display:flex; align-items:center; gap:6px;">
                                            <i class="bi bi-dot" style="color:#10b981; font-size:1.2rem;"></i>
                                            <?php echo htmlspecialchars($item['nome']); ?>
                                        </div>
                                    <?php 
                                        endif;
                                    } 
                                    ?>
                                </div>
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
                <select name="calculo_servico_id" id="inputCalculoServico" class="form-control" onchange="preencherDadosCalculo()">
                    <option value="" data-nome="" data-preco="0">Nenhum (preencher manual)</option>
                    <?php foreach ($calculos as $c): ?>
                        <option value="<?php echo $c['id']; ?>" 
                                data-nome="<?php echo htmlspecialchars($c['nome_servico']); ?>" 
                                data-preco="<?php echo $c['valor_cobrado']; ?>">
                            <?php echo htmlspecialchars($c['nome_servico']); ?> - R$ <?php echo number_format($c['valor_cobrado'], 2, ',', '.'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Área de pacote: serviço base + qtd sessões + desconto -->
            <div id="areaPacote" style="display:none;">
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-scissors"></i>
                        Serviço base
                    </label>
                    <select name="servico_base_id" id="inputServicoBase" class="form-control" onchange="calcularPacote()">
                        <option value="">Selecione o serviço...</option>
                        <?php foreach($listaServicos as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                    data-preco="<?= $s['preco'] ?>"
                                    data-nome="<?= htmlspecialchars($s['nome']) ?>">
                                <?= htmlspecialchars($s['nome']) ?> — R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dual-input-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label"><i class="bi bi-hash"></i> Qtd de sessões</label>
                        <input type="number" name="qtd_sessoes" id="inputQtdSessoes" class="form-control"
                               min="1" value="4" oninput="calcularPacote()">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label"><i class="bi bi-tag"></i> Valor unitário</label>
                        <input type="text" id="displayValorUnit" class="form-control" disabled
                               style="background:#f1f5f9; color:#64748b;" placeholder="R$ 0,00">
                    </div>
                </div>

                <!-- Desconto -->
                <div class="dual-input-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label"><i class="bi bi-percent"></i> Desconto</label>
                        <select name="desconto_tipo" id="inputDescontoTipo" class="form-control" onchange="calcularPacote()">
                            <option value="percentual">Percentual (%)</option>
                            <option value="valor">Valor fixo (R$)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">&nbsp;</label>
                        <input type="number" step="0.01" min="0" name="desconto_valor" id="inputDescontoValor"
                               class="form-control" placeholder="0" value="0" oninput="calcularPacote()">
                    </div>
                </div>

                <!-- Resumo do pacote -->
                <div id="resumoPacote" style="display:none; background:#f0fdf4; border:1px solid #86efac; border-radius:12px; padding:12px; margin-bottom:4px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:0.8rem; color:#64748b;">Preço sem desconto:</span>
                        <span id="displayPrecoOriginal" style="font-weight:700; color:#166534;">R$ 0,00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:0.8rem; color:#64748b;">Desconto:</span>
                        <span id="displayDesconto" style="font-weight:700; color:#dc2626;">- R$ 0,00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:1px solid #86efac;">
                        <strong style="font-size:0.875rem;">Valor final:</strong>
                        <strong id="displayPrecoFinal" style="font-size:1rem; color:#166534;">R$ 0,00</strong>
                    </div>
                </div>
                <input type="hidden" name="preco_original" id="inputPrecoOriginal" value="0">
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
    const areaPacote    = document.getElementById('areaPacote');

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

        // Resetar campos de pacote
        const selBase = document.getElementById('inputServicoBase');
        if (selBase) selBase.value = '';
        const inputQtd = document.getElementById('inputQtdSessoes');
        if (inputQtd) inputQtd.value = 4;
        document.getElementById('inputDescontoTipo').value = 'percentual';
        document.getElementById('inputDescontoValor').value = 0;
        document.getElementById('inputPrecoOriginal').value = 0;
        const resumo = document.getElementById('resumoPacote');
        if (resumo) resumo.style.display = 'none';

        inputTipo.value = tipo;

        if (tipo === 'pacote') {
            modalTitle.innerText = 'Novo Pacote';
            areaPacote.style.display = 'block';
        } else {
            modalTitle.innerText = 'Novo Serviço';
            areaPacote.style.display = 'none';
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

        // Carregar cálculo de custos vinculado
        const selectCalculo = document.getElementById('inputCalculoServico');
        if (selectCalculo && dados.calculo_servico_id) {
            selectCalculo.value = dados.calculo_servico_id;
        } else if (selectCalculo) {
            selectCalculo.value = '';
        }

        // Carregar dados de desconto (pacote)
        document.getElementById('inputDescontoTipo').value = dados.desconto_tipo || 'percentual';
        document.getElementById('inputDescontoValor').value = dados.desconto_valor || 0;
        document.getElementById('inputPrecoOriginal').value = dados.preco_original || 0;

        if (dados.tipo === 'pacote') {
            modalTitle.innerText = 'Editar Pacote';
            areaPacote.style.display = 'block';

            // Selecionar serviço base
            const selBase = document.getElementById('inputServicoBase');
            if (selBase && dados.itens_pacote) {
                selBase.value = dados.itens_pacote.split(',')[0] || '';
            }
            // Preencher quantidade de sessões
            const inputQtd = document.getElementById('inputQtdSessoes');
            if (inputQtd && dados.qtd_sessoes) {
                inputQtd.value = dados.qtd_sessoes;
            }

            calcularPacote();
        } else {
            modalTitle.innerText = 'Editar Serviço';
            areaPacote.style.display = 'none';
        }

        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // PREENCHER DADOS DO CÁLCULO AUTOMATICAMENTE
    function preencherDadosCalculo() {
        const selectCalculo = document.getElementById('inputCalculoServico');
        const selectedOption = selectCalculo.options[selectCalculo.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            const nomeCalculo = selectedOption.getAttribute('data-nome');
            const precoCalculo = selectedOption.getAttribute('data-preco');
            
            // Preencher nome se estiver vazio ou for modo criar
            if (inputAcao.value === 'create' || !inputNome.value) {
                inputNome.value = nomeCalculo;
            }
            
            // Preencher preço
            inputPreco.value = parseFloat(precoCalculo).toFixed(2);
        }
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

    // 5. CÁLCULO AUTOMÁTICO DE PACOTE (modelo simplificado: servico_base × qtd)
    function calcularPacote() {
        if (inputTipo.value !== 'pacote') return;

        const sel = document.getElementById('inputServicoBase');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        const precoUnit = opt ? (parseFloat(opt.getAttribute('data-preco')) || 0) : 0;
        const qtd = parseInt(document.getElementById('inputQtdSessoes')?.value) || 1;

        // Exibe valor unitário
        const displayUnit = document.getElementById('displayValorUnit');
        if (displayUnit) displayUnit.value = precoUnit > 0 ? 'R$ ' + precoUnit.toFixed(2).replace('.', ',') : '';

        const precoOriginal = precoUnit * qtd;
        document.getElementById('inputPrecoOriginal').value = precoOriginal.toFixed(2);

        // Aplica desconto
        const descontoTipo  = document.getElementById('inputDescontoTipo')?.value || 'percentual';
        const descontoValor = parseFloat(document.getElementById('inputDescontoValor')?.value) || 0;
        let valorDesconto = 0;
        let precoFinal = precoOriginal;
        if (descontoValor > 0) {
            if (descontoTipo === 'percentual') {
                valorDesconto = (precoOriginal * descontoValor) / 100;
            } else {
                valorDesconto = descontoValor;
            }
            precoFinal = Math.max(0, precoOriginal - valorDesconto);
        }

        // Mostra resumo
        const resumo = document.getElementById('resumoPacote');
        if (resumo) resumo.style.display = precoOriginal > 0 ? 'block' : 'none';
        document.getElementById('displayPrecoOriginal').innerText = 'R$ ' + precoOriginal.toFixed(2).replace('.', ',');
        document.getElementById('displayDesconto').innerText      = '- R$ ' + valorDesconto.toFixed(2).replace('.', ',');
        document.getElementById('displayPrecoFinal').innerText    = 'R$ ' + precoFinal.toFixed(2).replace('.', ',');
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

    // 8. CALCULAR PACOTE AO ALTERAR CAMPOS
    document.addEventListener('DOMContentLoaded', function() {
        const selBase = document.getElementById('inputServicoBase');
        const inputQtd = document.getElementById('inputQtdSessoes');
        const inputDescontoTipo = document.getElementById('inputDescontoTipo');
        const inputDescontoValor = document.getElementById('inputDescontoValor');
        if (selBase) selBase.addEventListener('change', calcularPacote);
        if (inputQtd) inputQtd.addEventListener('input', calcularPacote);
        if (inputDescontoTipo) inputDescontoTipo.addEventListener('change', calcularPacote);
        if (inputDescontoValor) inputDescontoValor.addEventListener('input', calcularPacote);
    });
</script>
