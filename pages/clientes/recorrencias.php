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
    /* ESTILO PADRÃO DO PAINEL
       - Fonte base pequena e delicada (0.8rem–0.9rem)
       - Estilo clean, moderno, bordas bem arredondadas
       - Nada de background degradê no body, só fundo neutro
       - Cards brancos com leve sombra e borda suave
       - Tudo responsivo: ajustar padding e fonte no mobile
    */

    :root {
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --accent: #ec4899;
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-soft: 0 6px 18px rgba(15,23,42,0.06);
        --shadow-hover: 0 14px 30px rgba(15,23,42,0.10);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: transparent;
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 0.85rem;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.5;
    }
    
    .container-recorrencias {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px 12px 100px 12px;
    }
    
    .header-page {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 18px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    
    .btn-voltar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 14px;
        background: #f9fafb;
        color: var(--text-main);
        text-decoration: none;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }
    
    .btn-voltar:hover {
        background: var(--border-color);
        transform: translateX(-2px);
    }
    
    .serie-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 16px 18px;
        margin-bottom: 12px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.20);
        transition: all 0.25s ease;
    }
    
    .serie-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-1px);
        border-color: rgba(79,70,229,0.25);
    }
    
    .serie-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 14px;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .serie-titulo {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .serie-titulo i {
        color: var(--primary-color);
        font-size: 1rem;
    }
    
    .serie-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        color: var(--text-muted);
    }
    
    .info-item i {
        color: var(--primary-color);
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .info-item strong {
        color: var(--text-main);
    }
    
    .serie-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .badge-ativo {
        background: #dcfce7;
        color: #16a34a;
    }
    
    .badge-inativo {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .serie-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        border: none;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-action:hover {
        transform: translateY(-1px);
    }

    .btn-action:active {
        transform: scale(0.96);
    }
    
    .btn-ver {
        background: rgba(79,70,229,0.08);
        color: var(--primary-color);
    }
    
    .btn-ver:hover {
        background: rgba(79,70,229,0.15);
    }
    
    .btn-cancelar {
        background: rgba(239,68,68,0.08);
        color: #ef4444;
    }
    
    .btn-cancelar:hover {
        background: rgba(239,68,68,0.15);
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        background: var(--bg-card);
        border-radius: 18px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.20);
    }
    
    .empty-state i {
        font-size: 3.5rem;
        color: #cbd5e1;
        margin-bottom: 14px;
        display: block;
        opacity: 0.4;
    }
    
    .empty-state h3 {
        color: var(--text-main);
        margin: 0 0 8px 0;
        font-size: 0.95rem;
        font-weight: 700;
    }
    
    .empty-state p {
        color: var(--text-muted);
        margin: 0;
        font-size: 0.8rem;
    }
    
    .tipo-recorrencia {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(79,70,229,0.08);
        color: var(--primary-color);
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .tipo-recorrencia i {
        font-size: 0.8rem;
    }

    /* Responsivo Mobile */
    @media (max-width: 768px) {
        .container-recorrencias {
            padding: 18px 10px 100px 10px;
        }

        .header-page {
            padding: 14px 16px;
            border-radius: 14px;
            flex-direction: column;
            align-items: stretch;
        }

        .header-page h1 {
            font-size: 1.15rem !important;
        }

        .header-page p {
            font-size: 0.75rem !important;
        }

        .btn-voltar {
            width: 100%;
            justify-content: center;
        }

        .serie-card {
            padding: 14px 16px;
            border-radius: 16px;
        }

        .serie-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .serie-titulo {
            font-size: 0.9rem;
        }

        .serie-info {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .info-item {
            font-size: 0.75rem;
        }

        .serie-actions {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
            justify-content: center;
        }

        .empty-state {
            padding: 40px 16px;
        }

        .empty-state i {
            font-size: 3rem;
        }
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
