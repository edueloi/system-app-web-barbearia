<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/recorrencia_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProd ? '/login' : '../../login.php'));
    exit;
}

$userId = $_SESSION['user_id'];
include '../../includes/db.php';

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
$clientesUrl = $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php';

// Buscar informações do cliente
$stmtCliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND user_id = ?");
$stmtCliente->execute([$clienteId, $userId]);
$cliente = $stmtCliente->fetch();

if (!$cliente) {
    header("Location: {$clientesUrl}");
    exit;
}

// Processar ações
if (isset($_GET['action']) && isset($_GET['serie_id'])) {
    $serieId = $_GET['serie_id'];
    $action = $_GET['action'];
    
    if ($action === 'cancelar_serie') {
        $resultado = cancelarSerieCompleta($pdo, $serieId, $userId);
        header("Location: " . $_SERVER['PHP_SELF'] . "?cliente_id={$clienteId}&msg=" . ($resultado['sucesso'] ? 'success' : 'error'));
        exit;
    }
}

// Buscar séries recorrentes ativas do cliente
$stmtSeries = $pdo->prepare("
    SELECT ar.*,
           (SELECT COUNT(*) FROM agendamentos WHERE serie_id = ar.serie_id) as total_agendamentos,
           (SELECT COUNT(*) FROM agendamentos WHERE serie_id = ar.serie_id AND data_agendamento >= date('now')) as proximos,
           (SELECT MIN(data_agendamento) FROM agendamentos WHERE serie_id = ar.serie_id AND data_agendamento >= date('now')) as proximo_agendamento
    FROM agendamentos_recorrentes ar
    WHERE ar.user_id = ?
      AND (ar.cliente_id = ? OR ar.cliente_nome = ?)
      AND ar.ativo = 1
    ORDER BY ar.criado_em DESC
");
$stmtSeries->execute([$userId, $clienteId, $cliente['nome']]);
$series = $stmtSeries->fetchAll();

$pageTitle = 'Agendamentos Recorrentes - ' . htmlspecialchars($cliente['nome']);
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<style>
    body {
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        background: #f8fafc;
    }
    
    .container-recorrencias {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px 16px 100px;
    }
    
    .header-page {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .btn-voltar {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f1f5f9;
        color: #0f172a;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    
    .btn-voltar:hover {
        background: #e2e8f0;
        transform: translateX(-4px);
    }
    
    .serie-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06);
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    
    .serie-card:hover {
        box-shadow: 0 8px 24px rgba(99,102,241,0.15);
        transform: translateY(-2px);
    }
    
    .serie-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    
    .serie-titulo {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 4px 0;
    }
    
    .serie-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: #64748b;
    }
    
    .info-item i {
        color: #6366f1;
        font-size: 1rem;
    }
    
    .serie-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-ativo {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-inativo {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .serie-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        border: none;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    
    .btn-ver {
        background: #ede9fe;
        color: #6b21a8;
    }
    
    .btn-ver:hover {
        background: #ddd6fe;
        transform: translateY(-2px);
    }
    
    .btn-cancelar {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .btn-cancelar:hover {
        background: #fecaca;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 16px;
    }
    
    .empty-state h3 {
        color: #0f172a;
        margin: 0 0 8px 0;
    }
    
    .empty-state p {
        color: #64748b;
        margin: 0;
    }
    
    .tipo-recorrencia {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f0f9ff;
        color: #0369a1;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

<div class="container-recorrencias">
    <div class="header-page">
        <a href="<?php echo $clientesUrl; ?>" class="btn-voltar">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>
        <div style="flex:1;">
            <h1 style="margin:0; font-size:1.5rem; color:#0f172a;">
                <i class="bi bi-arrow-repeat"></i>
                Agendamentos Recorrentes
            </h1>
            <p style="margin:4px 0 0 0; color:#64748b; font-size:0.9rem;">
                <?php echo htmlspecialchars($cliente['nome']); ?>
            </p>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div style="padding:12px 16px; background:<?php echo $_GET['msg'] === 'success' ? '#d1fae5' : '#fee2e2'; ?>; border-radius:12px; margin-bottom:20px; color:<?php echo $_GET['msg'] === 'success' ? '#065f46' : '#991b1b'; ?>;">
            <i class="bi bi-<?php echo $_GET['msg'] === 'success' ? 'check-circle-fill' : 'x-circle-fill'; ?>"></i>
            <?php echo $_GET['msg'] === 'success' ? 'Operação realizada com sucesso!' : 'Erro ao realizar operação.'; ?>
        </div>
    <?php endif; ?>

    <?php if (count($series) > 0): ?>
        <?php foreach ($series as $serie): ?>
            <?php
            $tipoTexto = [
                'diaria' => 'Diária',
                'semanal' => 'Semanal',
                'quinzenal' => 'Quinzenal',
                'mensal_dia' => 'Mensal (dia fixo)',
                'mensal_semana' => 'Mensal (semana)',
                'personalizada' => 'Personalizada'
            ];
            $tipoLabel = $tipoTexto[$serie['tipo_recorrencia']] ?? 'Desconhecida';
            ?>
            
            <div class="serie-card">
                <div class="serie-header">
                    <div>
                        <h3 class="serie-titulo">
                            <i class="bi bi-calendar-event"></i>
                            <?php echo htmlspecialchars($serie['servico_nome']); ?>
                        </h3>
                        <span class="tipo-recorrencia">
                            <i class="bi bi-repeat"></i>
                            <?php echo $tipoLabel; ?>
                        </span>
                    </div>
                    <span class="serie-badge <?php echo $serie['ativo'] ? 'badge-ativo' : 'badge-inativo'; ?>">
                        <?php echo $serie['ativo'] ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </div>
                
                <div class="serie-info">
                    <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <strong>Horário:</strong> <?php echo date('H:i', strtotime($serie['horario'])); ?>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-currency-dollar"></i>
                        <strong>Valor:</strong> R$ <?php echo number_format($serie['valor'], 2, ',', '.'); ?>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-calendar-range"></i>
                        <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($serie['data_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($serie['data_fim'])); ?>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-hash"></i>
                        <strong>Total:</strong> <?php echo $serie['qtd_total']; ?> ocorrências
                    </div>
                    <div class="info-item">
                        <i class="bi bi-calendar-check"></i>
                        <strong>Agendados:</strong> <?php echo $serie['total_agendamentos']; ?>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-calendar-plus"></i>
                        <strong>Próximos:</strong> <?php echo $serie['proximos']; ?>
                    </div>
                </div>
                
                <?php if ($serie['proximo_agendamento']): ?>
                    <div style="padding:10px; background:#f0f9ff; border-left:4px solid #0ea5e9; border-radius:8px; margin-bottom:12px;">
                        <strong style="color:#0369a1; font-size:0.85rem;">
                            <i class="bi bi-calendar-event"></i>
                            Próximo agendamento: <?php echo date('d/m/Y', strtotime($serie['proximo_agendamento'])); ?> às <?php echo date('H:i', strtotime($serie['horario'])); ?>
                        </strong>
                    </div>
                <?php endif; ?>
                
                <div class="serie-actions">
                    <a href="<?php echo $isProd ? '/agenda' : '../agenda/agenda.php'; ?>?serie_id=<?php echo $serie['serie_id']; ?>" class="btn-action btn-ver">
                        <i class="bi bi-eye"></i>
                        Ver na agenda
                    </a>
                    
                    <?php if ($serie['ativo'] && $serie['proximos'] > 0): ?>
                        <button onclick="confirmarCancelarSerie('<?php echo $serie['serie_id']; ?>', '<?php echo htmlspecialchars($serie['servico_nome']); ?>')" class="btn-action btn-cancelar">
                            <i class="bi bi-x-circle"></i>
                            Cancelar série
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h3>Nenhum agendamento recorrente</h3>
            <p>Este cliente não possui agendamentos recorrentes ativos.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function confirmarCancelarSerie(serieId, servicoNome) {
        if (confirm(`Tem certeza que deseja cancelar TODOS os agendamentos futuros da série "${servicoNome}"?\n\nEsta ação não pode ser desfeita.`)) {
            window.location.href = `?cliente_id=<?php echo $clienteId; ?>&action=cancelar_serie&serie_id=${serieId}`;
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>
