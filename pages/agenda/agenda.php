<?php
// 1. Configurações Iniciais
$pageTitle = 'Minha Agenda';
include '../../includes/header.php'; // Atenção aos caminhos (voltar 2 pastas)
include '../../includes/menu.php';
include '../../includes/db.php';     // Conexão ao banco

// --- SIMULAÇÃO DE USUÁRIO (Enquanto não criamos o login.php real com banco) ---
// Isto garante que o código funciona agora. Depois removemos quando tivermos login real.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID fictício para teste
}
$userId = $_SESSION['user_id'];

// 2. Lógica de Data (Navegação Dia Anterior / Próximo Dia)
$dataExibida = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$diaSemana = strftime('%A', strtotime($dataExibida)); // (Opcional: formatar dia da semana)

// Calcular dia anterior e próximo para os botões
$dataAnt = date('Y-m-d', strtotime($dataExibida . ' -1 day'));
$dataPro = date('Y-m-d', strtotime($dataExibida . ' +1 day'));

// 3. Processar Formulário (Criar Novo Agendamento)
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
    $cliente = $_POST['cliente'];
    $servico = $_POST['servico'];
    $horario = $_POST['horario'];
    $obs     = $_POST['obs'];

    if (!empty($cliente) && !empty($horario)) {
        $sql = "INSERT INTO agendamentos (user_id, cliente_nome, servico, data_agendamento, horario, observacoes, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Confirmado')";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId, $cliente, $servico, $dataExibida, $horario, $obs])) {
            // Recarrega a página para limpar o POST e mostrar o novo item
            echo "<script>window.location.href='agenda.php?data=$dataExibida';</script>";
            exit;
        } else {
            $mensagem = "Erro ao salvar!";
        }
    }
}

// 4. Buscar Agendamentos do Dia (Apenas deste usuário)
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE user_id = ? AND data_agendamento = ? ORDER BY horario ASC");
$stmt->execute([$userId, $dataExibida]);
$agendamentos = $stmt->fetchAll();
?>

<style>
    /* Estilos Específicos da Agenda */
    .agenda-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        padding: 15px;
        border-radius: 16px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }

    .date-display {
        text-align: center;
    }
    .date-display h3 { margin: 0; font-size: 1.1rem; color: var(--text-dark); }
    .date-display span { font-size: 0.85rem; color: var(--text-gray); }

    .btn-nav {
        background: #f1f5f9;
        border: none;
        width: 40px; height: 40px;
        border-radius: 50%;
        color: var(--text-dark);
        cursor: pointer;
        font-size: 1.2rem;
        display: flex; align-items: center; justify-content: center;
        text-decoration: none;
        transition: 0.2s;
    }
    .btn-nav:hover { background: #e2e8f0; color: var(--primary); }

    /* Botão Flutuante (FAB) para Adicionar */
    .fab-add {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px; height: 60px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        border: none;
        font-size: 2rem;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.2s;
        z-index: 900;
    }
    .fab-add:hover { transform: scale(1.1); background: var(--primary-hover); }

    /* Lista de Agendamentos */
    .timeline {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .event-card {
        display: flex;
        background: white;
        border-radius: 16px;
        padding: 15px;
        box-shadow: var(--shadow);
        border-left: 5px solid var(--primary);
        transition: 0.2s;
    }
    
    .event-time {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding-right: 15px;
        border-right: 1px solid #f1f5f9;
        min-width: 60px;
    }
    .event-time strong { font-size: 1.2rem; color: var(--text-dark); }
    .event-time span { font-size: 0.8rem; color: var(--text-gray); }

    .event-details {
        padding-left: 15px;
        flex-grow: 1;
    }
    .client-name { font-weight: 700; font-size: 1rem; margin-bottom: 4px; display: block; }
    .service-name { color: var(--text-gray); font-size: 0.9rem; display: block; }
    .obs-text { font-size: 0.8rem; color: #94a3b8; margin-top: 5px; font-style: italic; }

    /* Estado Vazio */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-gray);
    }
    .empty-state i { font-size: 3rem; margin-bottom: 10px; display: block; opacity: 0.3; }

    /* Modal (Janela de Cadastro) */
    .modal-overlay {
        display: none;
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 2000;
        align-items: center; justify-content: center;
        backdrop-filter: blur(3px);
    }
    .modal-overlay.active { display: flex; }

    .modal-box {
        background: white;
        padding: 25px;
        border-radius: 20px;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        animation: slideUp 0.3s ease;
    }
    @keyframes slideUp { from {transform: translateY(20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }

    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-size: 0.9rem; font-weight: 600; color: var(--text-dark); }
    .form-control {
        width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px;
        font-family: 'Inter', sans-serif; font-size: 1rem; box-sizing: border-box;
    }
    .form-control:focus { outline: none; border-color: var(--primary); }
    
    .btn-submit {
        width: 100%; background: var(--primary); color: white; padding: 14px;
        border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer;
    }
    .btn-close-modal {
        background: transparent; border: none; float: right; font-size: 1.5rem; cursor: pointer; color: #94a3b8;
    }
</style>

<main class="main-content">

    <div class="agenda-controls">
        <a href="?data=<?php echo $dataAnt; ?>" class="btn-nav"><i class="bi bi-chevron-left"></i></a>
        
        <div class="date-display">
            <h3><?php echo date('d/m/Y', strtotime($dataExibida)); ?></h3>
            <span>Agenda do Dia</span>
        </div>
        
        <a href="?data=<?php echo $dataPro; ?>" class="btn-nav"><i class="bi bi-chevron-right"></i></a>
    </div>

    <div class="timeline">
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach ($agendamentos as $agenda): ?>
                <div class="event-card">
                    <div class="event-time">
                        <strong><?php echo date('H:i', strtotime($agenda['horario'])); ?></strong>
                        <span>h</span>
                    </div>
                    <div class="event-details">
                        <span class="client-name"><?php echo htmlspecialchars($agenda['cliente_nome']); ?></span>
                        <span class="service-name"><i class="bi bi-scissors"></i> <?php echo htmlspecialchars($agenda['servico']); ?></span>
                        <?php if(!empty($agenda['observacoes'])): ?>
                            <div class="obs-text"><?php echo htmlspecialchars($agenda['observacoes']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>Nenhum cliente agendado para hoje.</p>
                <small>Clique no botão + para adicionar.</small>
            </div>
        <?php endif; ?>
    </div>

</main>

<button class="fab-add" onclick="abrirModal()">
    <i class="bi bi-plus"></i>
</button>

<div class="modal-overlay" id="modalAgendamento">
    <div class="modal-box">
        <button class="btn-close-modal" onclick="fecharModal()">&times;</button>
        <h3 style="margin-top:0; margin-bottom: 20px;">Novo Agendamento</h3>
        
        <form method="POST" action="agenda.php?data=<?php echo $dataExibida; ?>">
            <input type="hidden" name="novo_agendamento" value="1">
            
            <div class="form-group">
                <label>Nome do Cliente</label>
                <input type="text" name="cliente" class="form-control" placeholder="Ex: Maria Silva" required>
            </div>

            <div class="form-group">
                <label>Serviço</label>
                <input type="text" name="servico" class="form-control" placeholder="Ex: Corte e Pintura" required>
            </div>

            <div class="form-group">
                <label>Horário</label>
                <input type="time" name="horario" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Observações (Opcional)</label>
                <textarea name="obs" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn-submit">Confirmar Agendamento</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    // Controle do Modal
    const modal = document.getElementById('modalAgendamento');

    function abrirModal() {
        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // Fechar ao clicar fora
    modal.addEventListener('click', (e) => {
        if (e.target === modal) fecharModal();
    });
</script>