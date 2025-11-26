<?php
// agendar.php (NA RAIZ DO PROJETO)
include 'includes/db.php';

// ID do Profissional dinâmico via GET
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
if ($profissionalId <= 0) {
    die('<h2>Profissional não encontrado.</h2>');
}

// flag de sucesso (após redirect)
$sucesso = isset($_GET['ok']) && $_GET['ok'] == 1;

// =========================================================
// API INTERNA (AJAX)
// =========================================================

// 1. Buscar Horários
if (isset($_GET['action']) && $_GET['action'] === 'buscar_horarios') {
    $data           = $_GET['data'];
    $duracaoServico = (int)$_GET['duracao'];
    $diaSemana      = date('w', strtotime($data));

    // Turnos de trabalho
    $stmt = $pdo->prepare("SELECT inicio, fim FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?");
    $stmt->execute([$profissionalId, $diaSemana]);
    $turnos = $stmt->fetchAll();

    // Horários ocupados
    $stmt = $pdo->prepare("SELECT horario, servico FROM agendamentos WHERE user_id = ? AND data_agendamento = ? AND status != 'Cancelado'");
    $stmt->execute([$profissionalId, $data]);
    $ocupados = $stmt->fetchAll();

    $minutosOcupados = [];
    foreach ($ocupados as $ag) {
        $horaMin   = explode(':', $ag['horario']);
        $inicioMin = ((int)$horaMin[0] * 60) + (int)$horaMin[1];
        $fimMin    = $inicioMin + $duracaoServico; // bloqueia pelo tempo do serviço

        for ($m = $inicioMin; $m < $fimMin; $m++) {
            $minutosOcupados[$m] = true;
        }
    }

    $slotsDisponiveis = [];
    foreach ($turnos as $turno) {
        $inicioParts = explode(':', $turno['inicio']);
        $fimParts    = explode(':', $turno['fim']);
        $inicioMin   = ((int)$inicioParts[0] * 60) + (int)$inicioParts[1];
        $fimMin      = ((int)$fimParts[0] * 60) + (int)$fimParts[1];

        for ($time = $inicioMin; $time <= ($fimMin - $duracaoServico); $time += 30) {
            $livre = true;
            for ($check = $time; $check < ($time + $duracaoServico); $check++) {
                if (isset($minutosOcupados[$check])) {
                    $livre = false;
                    break;
                }
            }
            if ($livre) {
                $horaFormatada =
                    str_pad(floor($time / 60), 2, '0', STR_PAD_LEFT) . ':' .
                    str_pad($time % 60, 2, '0', STR_PAD_LEFT);
                $slotsDisponiveis[] = $horaFormatada;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($slotsDisponiveis);
    exit;
}

// 2. Buscar Cliente por CPF (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'buscar_cliente') {
    $cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE cpf = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$cpf, $profissionalId]);
    $cliente = $stmt->fetch();

    header('Content-Type: application/json');
    if ($cliente) {
        echo json_encode([
            'found'    => true,
            'nome'     => $cliente['nome'],
            'telefone' => $cliente['telefone']
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

// =========================================================
// PROCESSAR O AGENDAMENTO (POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = $_POST['cliente_nome']      ?? '';
    $telefone   = $_POST['cliente_telefone']  ?? '';
    $cpf        = preg_replace('/[^0-9]/', '', $_POST['cliente_cpf'] ?? '');
    $obsCliente = $_POST['cliente_obs']       ?? '';

    $servicoId  = $_POST['servico_id']        ?? null;
    $data       = $_POST['data_escolhida']    ?? '';
    $horario    = $_POST['horario_escolhido'] ?? '';

    // Recupera nome do serviço (do próprio profissional)
    $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$servicoId, $profissionalId]);
    $servicoNome = $stmt->fetchColumn();

    if ($nome && $horario && $servicoNome && $cpf) {
        // 1. Verifica/Salva o Cliente na tabela de CLIENTES
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf = ? AND user_id = ?");
        $stmt->execute([$cpf, $profissionalId]);
        $clienteExistente = $stmt->fetch();

        if ($clienteExistente) {
            $clienteId = $clienteExistente['id'];
            $pdo->prepare("UPDATE clientes SET nome = ?, telefone = ? WHERE id = ?")
                ->execute([$nome, $telefone, $clienteId]);
        } else {
            $pdo->prepare("INSERT INTO clientes (user_id, nome, telefone, cpf) VALUES (?, ?, ?, ?)")
                ->execute([$profissionalId, $nome, $telefone, $cpf]);
            $clienteId = $pdo->lastInsertId();
        }

        // 2. Cria o Agendamento vinculado
        $sql = "INSERT INTO agendamentos 
                (user_id, cliente_id, cliente_nome, cliente_cpf, servico, data_agendamento, horario, status, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)";

        $obsFinal = $obsCliente;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $profissionalId,
            $clienteId,
            $nome,
            $cpf,
            $servicoNome,
            $data,
            $horario,
            $obsFinal
        ]);

        // Redireciona para evitar re-envio de formulário e mostrar tela de sucesso
        header("Location: agendar.php?user={$profissionalId}&ok=1");
        exit;
    }
}

// Buscar Serviços para exibir
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY nome ASC");
$stmt->execute([$profissionalId]);
$servicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Agendar - Salão Top</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary: #6366f1; --bg: #f8fafc; --text: #1e293b; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; }

        .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }

        .header { background: var(--primary); padding: 30px 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 1.4rem; }
        .header p { opacity: 0.9; font-size: 0.9rem; margin-top: 5px; }

        .steps-bar { display: flex; background: #f1f5f9; padding: 10px; gap: 5px; }
        .step-dot { flex: 1; height: 4px; background: #cbd5e1; border-radius: 2px; }
        .step-dot.active { background: var(--primary); }

        .step-container { padding: 25px; display: none; animation: fadeIn 0.3s; }
        .step-container.active { display: block; }
        .step-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 5px; }
        .step-desc { font-size: 0.85rem; color: var(--muted); margin-bottom: 20px; }

        .service-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .service-card:hover { border-color: var(--primary); background: #eef2ff; }
        .service-card.selected { background: var(--primary); color: white; border-color: var(--primary); }
        .service-price { font-weight: 700; }

        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); outline: none; }

        .slots-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .time-slot { background: #f1f5f9; padding: 10px 5px; text-align: center; border-radius: 8px; cursor: pointer; font-size: 0.9rem; border: 1px solid transparent; }
        .time-slot:hover { background: #e2e8f0; }
        .time-slot.selected { background: var(--primary); color: white; font-weight: 600; }

        .btn-action { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-action:disabled { background: #cbd5e1; cursor: not-allowed; opacity: 0.7; }
        .btn-back { background: none; border: none; color: var(--muted); cursor: pointer; display: flex; align-items: center; gap: 5px; margin-bottom: 10px; font-size: 0.9rem; }

        .feedback-msg { font-size: 0.85rem; margin-top: 5px; display: none; }
        .text-success { color: #16a34a; }
        .text-error { color: #dc2626; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .success-screen { text-align: center; padding: 40px 20px; }
        .success-icon { font-size: 4rem; color: #22c55e; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <?php if ($sucesso): ?>
        <div class="success-screen">
            <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h2>Agendamento Confirmado!</h2>
            <p>Seu horário está reservado.</p>
            <a href="agendar.php?user=<?php echo $profissionalId; ?>" class="btn-action" style="display:inline-block; text-decoration:none;">Novo Agendamento</a>
        </div>
    <?php else: ?>

    <div class="header">
        <h1>Salão Top</h1>
        <p>Agendamento Online</p>
    </div>

    <div class="steps-bar">
        <div class="step-dot active" id="dot1"></div>
        <div class="step-dot" id="dot2"></div>
        <div class="step-dot" id="dot3"></div>
    </div>

    <form method="POST" id="bookingForm" onsubmit="return validarForm()">
        <input type="hidden" name="servico_id" id="inputServicoId">
        <input type="hidden" name="data_escolhida" id="inputData">
        <input type="hidden" name="horario_escolhido" id="inputHorario">

        <div class="step-container active" id="step1">
            <div class="step-title">1. Escolha o serviço</div>
            <div class="step-desc">Selecione o procedimento desejado.</div>

            <?php foreach ($servicos as $s): ?>
                <div class="service-card" onclick="selectService(this, <?php echo $s['id']; ?>, <?php echo $s['duracao']; ?>)">
                    <div>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($s['nome']); ?></div>
                        <div style="font-size:0.8rem; opacity:0.8;"><?php echo $s['duracao']; ?> min</div>
                    </div>
                    <div class="service-price">R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="step-container" id="step2">
            <button type="button" class="btn-back" onclick="goToStep(1)"><i class="bi bi-arrow-left"></i> Voltar</button>
            <div class="step-title">2. Escolha data e hora</div>

            <div class="form-group">
                <label>Data</label>
                <input type="date" class="form-control" id="datePicker" onchange="loadTimes()">
            </div>

            <label>Horários Livres</label>
            <div id="loadingTimes" style="display:none; color:var(--muted); font-size:0.85rem; margin-bottom:10px;">Buscando...</div>
            <div class="slots-grid" id="slotsContainer">
                <div style="grid-column: 1/-1; text-align:center; color:#94a3b8; font-size:0.9rem;">Selecione a data acima.</div>
            </div>

            <button type="button" class="btn-action" id="btnNextTo3" disabled onclick="goToStep(3)">Continuar</button>
        </div>

        <div class="step-container" id="step3">
            <button type="button" class="btn-back" onclick="goToStep(2)"><i class="bi bi-arrow-left"></i> Voltar</button>
            <div class="step-title">3. Seus Dados</div>
            <div class="step-desc">Informe o CPF para identificação rápida.</div>

            <div class="form-group">
                <label>CPF (Apenas números)</label>
                <input type="tel" name="cliente_cpf" id="cpfInput" class="form-control" placeholder="000.000.000-00" maxlength="14" oninput="mascaraCPF(this)" onblur="buscarCliente()">
                <div id="cpfFeedback" class="feedback-msg"></div>
            </div>

            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="cliente_nome" id="nomeInput" class="form-control" placeholder="Seu nome" required>
            </div>

            <div class="form-group">
                <label>Telefone / WhatsApp</label>
                <input type="tel" name="cliente_telefone" id="telInput" class="form-control" placeholder="(00) 00000-0000" onkeyup="mascaraTelefone(this)" required>
            </div>

            <div class="form-group">
                <label>Observação (Opcional)</label>
                <textarea name="cliente_obs" class="form-control" rows="2" placeholder="Ex: Tenho alergia a tal produto..."></textarea>
            </div>

            <div style="background:#f1f5f9; padding:15px; border-radius:10px; margin-top:10px; font-size:0.9rem;">
                <strong>Resumo:</strong><br>
                <span id="resumeServico">Serviço</span><br>
                <span id="resumeDataHora">Data e Hora</span>
            </div>

            <button type="submit" id="btnConfirmar" class="btn-action">Confirmar Agendamento</button>
        </div>

    </form>
    <?php endif; ?>
</div>

<script>
    const PROF_ID  = <?php echo $profissionalId; ?>;
    const BASE_URL = 'agendar.php?user=' + PROF_ID;

    let selectedDuration    = 0;
    let selectedServiceName = '';

    function goToStep(step) {
        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        document.querySelectorAll('.step-dot').forEach((el, idx) => {
            el.classList.toggle('active', idx < step);
        });
    }

    function selectService(card, id, duration) {
        document.querySelectorAll('.service-card').forEach(el => el.classList.remove('selected'));
        card.classList.add('selected');

        document.getElementById('inputServicoId').value = id;
        selectedDuration    = duration;
        selectedServiceName = card.querySelector('div div').innerText;

        document.getElementById('datePicker').min = new Date().toISOString().split("T")[0];
        goToStep(2);
    }

    function loadTimes() {
        const date      = document.getElementById('datePicker').value;
        const container = document.getElementById('slotsContainer');
        const loader    = document.getElementById('loadingTimes');
        const btnNext   = document.getElementById('btnNextTo3');

        if (!date) return;

        document.getElementById('inputData').value = date;
        container.innerHTML = '';
        loader.style.display = 'block';
        btnNext.disabled = true;

        fetch(`${BASE_URL}&action=buscar_horarios&data=${encodeURIComponent(date)}&duracao=${selectedDuration}`)
            .then(response => response.json())
            .then(times => {
                loader.style.display = 'none';
                if (!Array.isArray(times) || times.length === 0) {
                    container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#dc2626;">Sem horários livres.</div>';
                } else {
                    times.forEach(time => {
                        const div = document.createElement('div');
                        div.className = 'time-slot';
                        div.innerText = time;
                        div.onclick = () => selectTime(div, time);
                        container.appendChild(div);
                    });
                }
            })
            .catch(err => {
                loader.style.display = 'none';
                console.error('Erro ao buscar horários:', err);
                container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#dc2626;">Erro ao carregar horários.</div>';
            });
    }

    function selectTime(div, time) {
        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
        div.classList.add('selected');

        document.getElementById('inputHorario').value = time;
        document.getElementById('btnNextTo3').disabled = false;

        document.getElementById('resumeServico').innerText = selectedServiceName;
        const dataFormatada = document.getElementById('datePicker').value.split('-').reverse().join('/');
        document.getElementById('resumeDataHora').innerText = `${dataFormatada} às ${time}`;
    }

    function mascaraCPF(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        i.value = v;
    }

    function mascaraTelefone(i) {
        let v = i.value.replace(/\D/g,"");
        v = v.replace(/^(\d{2})(\d)/g,"($1) $2");
        v = v.replace(/(\d)(\d{4})$/,"$1-$2");
        i.value = v;
    }

    function buscarCliente() {
        const cpf       = document.getElementById('cpfInput').value;
        const feedback  = document.getElementById('cpfFeedback');
        const nomeInput = document.getElementById('nomeInput');
        const telInput  = document.getElementById('telInput');

        if (cpf.replace(/\D/g, "").length < 11) return;

        feedback.style.display = 'block';
        feedback.innerText     = 'Buscando cadastro...';
        feedback.className     = 'feedback-msg';

        fetch(`${BASE_URL}&action=buscar_cliente&cpf=${encodeURIComponent(cpf)}`)
            .then(r => r.json())
            .then(data => {
                if (data.found) {
                    feedback.innerText = 'Cliente encontrado! Dados preenchidos.';
                    feedback.classList.add('text-success');
                    nomeInput.value = data.nome;
                    telInput.value  = data.telefone;
                } else {
                    feedback.innerText = 'CPF não cadastrado. Preencha seus dados.';
                    feedback.classList.add('text-error');
                    nomeInput.value = '';
                    telInput.value  = '';
                    nomeInput.focus();
                }
            })
            .catch(err => {
                console.error('Erro ao buscar cliente:', err);
                feedback.innerText = 'Erro ao buscar CPF.';
                feedback.classList.add('text-error');
            });
    }

    function validarForm() {
        const btn = document.getElementById('btnConfirmar');
        if (btn) {
            btn.innerHTML = 'Enviando...';
            btn.disabled = true;
        }

        // desabilita o campo de data do passo 2, pra ele não participar da validação HTML5
        const datePicker = document.getElementById('datePicker');
        if (datePicker) {
            datePicker.disabled = true;
        }

        return true;
    }

</script>
</body>
</html>
