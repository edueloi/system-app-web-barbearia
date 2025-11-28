<?php
require_once __DIR__ . '/../../includes/config.php';
// --- 1. LÓGICA PHP (CRIAR, EDITAR, EXCLUIR) ---
include '../../includes/db.php';

// Simulação de User ID

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

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
    // Verifica se pertence ao usuário para segurança
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);
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
                       SET nome=?, preco=?, duracao=?, foto=?, observacao=?, itens_pacote=?, calculo_servico_id=? 
                     WHERE id=? AND user_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $preco, $duracao, $fotoPath, $obs, $itens, $calculoServicoId, $idEdit, $userId]);
        } else {
            // CRIAR NOVO
            $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote, calculo_servico_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens, $calculoServicoId]);
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
    :root {
        --primary: #6366f1;
        --primary-soft: #eef2ff;
        --primary-hover: #4f46e5;
        --danger: #ef4444;
        --bg-page: #f8fafc;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --border-soft: #e2e8f0;
        --shadow-soft: 0 12px 28px rgba(15,23,42,0.10);
        --radius-lg: 22px;
        --radius-md: 16px;
        --radius-pill: 999px;
    }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        background-color: var(--bg-page);
    }

    /* Container principal da página */
    .main-content {
        padding: 18px 14px 90px 14px;
        max-width: 1080px;
        margin: 0 auto;
        box-sizing: border-box;
    }

    @media (min-width: 768px) {
        .main-content {
            padding-inline: 20px;
        }
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 14px;
    }
    .page-title-wrap {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .page-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.01em;
    }
    .page-subtitle {
        margin: 0;
        font-size: 0.82rem;
        color: var(--text-gray);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* Botão "Novo" compacto */
    .btn-chip {
        border-radius: var(--radius-pill);
        border: none;
        padding: 8px 15px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        background: var(--primary);
        color: #fff;
        box-shadow: 0 10px 22px rgba(99,102,241,0.35);
        transition: 0.15s;
        white-space: nowrap;
    }
    .btn-chip i { font-size: 0.9rem; }
    .btn-chip:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .btn-chip:active {
        transform: translateY(1px) scale(0.97);
    }

    /* Barra de Pesquisa */
    .search-bar-container {
        position: relative;
        margin-bottom: 16px;
    }
    .search-input {
        width: 100%;
        padding: 11px 12px 11px 42px;
        border-radius: var(--radius-pill);
        border: 1px solid var(--border-soft);
        font-size: 0.86rem;
        box-sizing: border-box;
        background: #ffffff;
        box-shadow: 0 8px 20px rgba(15,23,42,0.04);
        color: var(--text-dark);
    }
    .search-input::placeholder {
        color: #94a3b8;
    }
    .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
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
        font-size: 0.9rem;
    }
    .tab-btn.active {
        background: #ffffff;
        color: var(--primary);
        box-shadow: 0 4px 12px rgba(15,23,42,0.12);
    }

    /* Grid de cartões */
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 14px;
    }
    @media (max-width: 768px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
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
        box-shadow: var(--shadow-soft);
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        position: relative;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        min-height: 210px;
    }
    .service-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 32px rgba(15,23,42,0.12);
    }
    .service-card:active {
        transform: scale(0.97);
    }

    /* Badge de tipo (Pacote) */
    .service-chip {
        position: absolute;
        left: 10px;
        top: 10px;
        font-size: 0.65rem;
        background: rgba(15,23,42,0.75);
        color: #fefce8;
        padding: 3px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* Botões de Ação no Card (Editar/Excluir) */
    .card-actions {
        position: absolute;
        top: 8px;
        right: 8px;
        display: flex;
        gap: 6px;
        z-index: 2;
    }
    .action-btn {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: rgba(255,255,255,0.96);
        border: none;
        box-shadow: 0 4px 10px rgba(148,163,184,0.45);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dark);
        font-size: 0.85rem;
        transition: transform 0.12s ease, background 0.12s ease;
    }
    .action-btn:hover {
        transform: scale(1.07);
        background: #ffffff;
    }
    .btn-edit { color: var(--primary); }
    .btn-delete { color: var(--danger); }

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
        background: var(--primary);
        color: white;
        padding: 11px 22px;
        border: none;
        border-radius: var(--radius-pill);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        box-shadow: 0 10px 25px rgba(99,102,241,0.35);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        letter-spacing: 0.01em;
    }
    .btn-submit i { font-size: 1rem; }
    .btn-submit:active {
        transform: scale(0.97);
    }

    /* Modal estilo app */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
        opacity: 0;
        transition: opacity 0.25s;
    }
    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    .modal-box {
        background: #ffffff;
        padding: 20px 18px 22px 18px;
        border-radius: 26px;
        width: 96%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 24px 40px rgba(15,23,42,0.28);
        transform: translateY(24px);
        transition: transform 0.25s;
    }
    .modal-overlay.active .modal-box {
        transform: translateY(0);
    }

    @media (max-width: 480px) {
        .modal-overlay {
            align-items: flex-end;
        }
        .modal-box {
            width: 100%;
            max-width: 100%;
            border-radius: 24px 24px 0 0;
            margin: 0;
            max-height: 86vh;
            padding-bottom: 26px;
        }
    }

    /* Formulário do modal */
    .form-group {
        margin-bottom: 11px;
    }
    .form-label {
        display: block;
        margin-bottom: 4px;
        font-weight: 600;
        font-size: 0.8rem;
        color: #334155;
    }
    .form-control {
        width: 100%;
        padding: 10px 11px;
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-md);
        box-sizing: border-box;
        font-size: 0.86rem;
        background: #f8fafc;
        transition: 0.15s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: #ffffff;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.15);
    }
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }

    /* Lista de serviços dentro do pacote */
    .checkbox-list {
        max-height: 160px;
        overflow-y: auto;
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-md);
        padding: 8px 9px;
        background: #f8fafc;
    }
    .check-item {
        padding: 6px 0;
        border-bottom: 1px solid #e5e7eb;
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size: 0.8rem;
        gap: 10px;
    }
    .check-item:last-child {
        border-bottom: none;
    }
    .check-item span {
        white-space: nowrap;
        color: var(--text-gray);
    }

    .modal-header-bar {
        width: 40px;
        height: 5px;
        background: #e2e8f0;
        border-radius: 999px;
        margin: 0 auto 16px auto;
    }
    .modal-header-row {
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom: 10px;
    }
    .modal-title {
        margin:0;
        font-size:1.05rem;
        font-weight:700;
        color:var(--text-dark);
        letter-spacing:-0.01em;
    }
    .modal-close-btn {
        background:none;
        border:none;
        font-size:1.5rem;
        cursor:pointer;
        color:#64748b;
        padding:2px 6px;
        border-radius:999px;
        transition:0.12s;
    }
    .modal-close-btn:hover {
        background:#e5e7eb;
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
</style>

<main class="main-content">

    <div class="page-header">
        <div class="page-title-wrap">
            <h1 class="page-title">Meus Serviços</h1>
            <p class="page-subtitle">Organize serviços e pacotes que seus clientes podem agendar.</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-chip" onclick="abrirModal('unico')">
                <i class="bi bi-plus-lg"></i>
                Novo serviço
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
                        <a href="<?php echo $servicosUrl; ?>?delete=<?php echo $s['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja apagar?');">
                            <i class="bi bi-trash-fill"></i>
                        </a>
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
                        <a href="<?php echo $servicosUrl; ?>?delete=<?php echo $p['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja apagar?');">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </div>

                    <div class="service-img" style="<?php echo $p['foto'] ? "background-image: url('../../{$p['foto']}')" : ""; ?>">
                        <?php if (!$p['foto']): ?>
                            <i class="bi bi-box-seam" style="font-size: 2.2rem;"></i>
                        <?php endif; ?>
                    </div>

                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($p['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i>
                            <?php echo $p['duracao']; ?> min
                        </div>
                        <div class="service-price">
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
                <label class="form-label">Nome do serviço / pacote</label>
                <input type="text" name="nome" id="inputNome" class="form-control" placeholder="Ex: Corte Degrade + Barba" required>
            </div>

            <div class="form-group">
                <label>Cálculo de custos vinculado (opcional)</label>
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
                <label class="form-label">Itens do pacote</label>
                <div class="checkbox-list">
                    <?php if(count($listaServicos) > 0): ?>
                        <?php foreach($listaServicos as $s): ?>
                            <div class="check-item">
                                <label style="display:flex; align-items:center; gap:8px; flex:1;">
                                    <input type="checkbox"
                                           name="itens_selecionados[]"
                                           value="<?php echo $s['id']; ?>"
                                           class="chk-servico"
                                           data-price="<?php echo $s['preco']; ?>"
                                           data-time="<?php echo $s['duracao']; ?>"
                                           onchange="calcularPacote()">
                                    <?php echo htmlspecialchars($s['nome']); ?>
                                </label>
                                <span>R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="font-size:0.78rem; color:var(--text-gray);">
                            Cadastre ao menos um serviço para montar pacotes.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dual-input-row">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="preco" id="inputPreco" class="form-control" placeholder="0,00" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Tempo total (min)</label>
                    <input type="number" name="duracao" id="inputTempo" class="form-control" placeholder="30" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Foto de capa</label>
                <input type="file" name="foto" class="form-control">
                <small style="color:#94a3b8; font-size:0.75rem;">
                    Imagens horizontais ficam mais bonitas no card (JPG, PNG ou WEBP).
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Descrição (opcional)</label>
                <textarea name="obs" id="inputObs" class="form-control" rows="2" placeholder="Detalhes, condições ou observações do serviço."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="btnSalvar">
                <i class="bi bi-check-circle-fill"></i>
                Salvar
            </button>
        </form>
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
        btnSalvar.innerText = 'Salvar';
        document.querySelectorAll('.chk-servico').forEach(chk => chk.checked = false);

        inputTipo.value = tipo;
        if (tipo === 'pacote') {
            modalTitle.innerText = 'Novo Pacote';
            areaItens.style.display = 'block';
        } else {
            modalTitle.innerText = 'Novo Serviço';
            areaItens.style.display = 'none';
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
        inputObs.value    = dados.observacao;
        inputFotoAtual.value = dados.foto;
        btnSalvar.innerText = 'Atualizar';

        if (dados.tipo === 'pacote') {
            modalTitle.innerText = 'Editar Pacote';
            areaItens.style.display = 'block';

            let itens = dados.itens_pacote ? dados.itens_pacote.split(',') : [];
            document.querySelectorAll('.chk-servico').forEach(chk => {
                chk.checked = itens.includes(chk.value);
            });
        } else {
            modalTitle.innerText = 'Editar Serviço';
            areaItens.style.display = 'none';
        }

        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // 3. PESQUISA INSTANTÂNEA
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

    // 4. CÁLCULO AUTOMÁTICO DE PACOTE
    function calcularPacote() {
        if (inputTipo.value !== 'pacote') return;

        let totalPreco = 0;
        let totalTempo = 0;

        document.querySelectorAll('.chk-servico:checked').forEach(chk => {
            totalPreco += parseFloat(chk.getAttribute('data-price')) || 0;
            totalTempo += parseInt(chk.getAttribute('data-time')) || 0;
        });

        if (inputAcao.value === 'create') {
            inputPreco.value = totalPreco.toFixed(2);
            inputTempo.value = totalTempo;
        }
    }

    // 5. TROCA DE ABAS
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
