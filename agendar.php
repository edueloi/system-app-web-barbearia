<?php
// =========================================================
// 1. CONFIGURAÇÃO E BACKEND
// =========================================================

// Ajuste o caminho do include conforme sua estrutura de pastas
$dbPath = 'includes/db.php';
if (!file_exists($dbPath)) $dbPath = '../../includes/db.php';
require_once $dbPath;

// ID do Profissional (Pega da URL)
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Se não tiver ID, tenta pegar o primeiro usuário (Fallback)
if ($profissionalId <= 0) {
    $stmtFirst = $pdo->query("SELECT id FROM usuarios LIMIT 1");
    $profissionalId = $stmtFirst->fetchColumn();
    if (!$profissionalId) {
        die('<div style="font-family:sans-serif;text-align:center;padding:50px;">Sistema indisponível.</div>');
    }
}

// Busca dados COMPLETOS do profissional/estabelecimento
$stmtProf = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmtProf->execute([$profissionalId]);
$profissional = $stmtProf->fetch();

if (!$profissional) die('Profissional não encontrado.');

// --- LÓGICA DE EXIBIÇÃO (Negócio vs Profissional) ---
$nomeEstabelecimento = !empty($profissional['estabelecimento']) ? $profissional['estabelecimento'] : $profissional['nome'];
$nomeProfissional    = $profissional['nome'];
$telefone            = !empty($profissional['telefone']) ? $profissional['telefone'] : '';
$biografia           = !empty($profissional['biografia']) ? $profissional['biografia'] : 'Agende seu horário com a gente!';

// Endereço Formatado
$enderecoCompleto = $profissional['endereco'] ?? '';
if (!empty($profissional['numero'])) $enderecoCompleto .= ', ' . $profissional['numero'];
if (!empty($profissional['bairro'])) $enderecoCompleto .= ' - ' . $profissional['bairro'];

// Foto / Logo
$fotoPerfil = 'assets/default-avatar.png'; // Fallback padrão se você tiver
$iniciais   = strtoupper(mb_substr($nomeEstabelecimento, 0, 1));
$temFoto    = false;

if (!empty($profissional['foto']) && file_exists($profissional['foto'])) {
    $fotoPerfil = $profissional['foto'];
    $temFoto = true;
} elseif (!empty($profissional['foto']) && file_exists('../../' . $profissional['foto'])) {
    $fotoPerfil = '../../' . $profissional['foto'];
    $temFoto = true;
}

// --- CONFIGURAÇÃO DE CORES (AGORA VEM DO BANCO) ---
$corPersonalizada = '#4f46e5'; // padrão

if (!empty($profissional['cor_tema'])) {
    $cor = trim($profissional['cor_tema']);

    // garante que começa com #
    if ($cor[0] !== '#') {
        $cor = '#' . $cor;
    }

    // valida formato #RRGGBB
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
        $corPersonalizada = $cor;
    }
}

$sucesso = isset($_GET['ok']) && $_GET['ok'] == 1;

// =========================================================
// 2. API AJAX (JSON)
// =========================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Buscar Horários
    if ($_GET['action'] === 'buscar_horarios') {
        $data           = $_GET['data'];
        $duracaoServico = (int)$_GET['duracao'];
        $diaSemana      = date('w', strtotime($data));

        $stmt = $pdo->prepare("SELECT inicio, fim FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?");
        $stmt->execute([$profissionalId, $diaSemana]);
        $turnos = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT horario FROM agendamentos WHERE user_id = ? AND data_agendamento = ? AND status != 'Cancelado'");
        $stmt->execute([$profissionalId, $data]);
        $ocupados = $stmt->fetchAll();

        $minutosOcupados = [];
        foreach ($ocupados as $ag) {
            $hm = explode(':', $ag['horario']);
            $inicioMin = ((int)$hm[0] * 60) + (int)$hm[1];
            for ($m = $inicioMin; $m < ($inicioMin + $duracaoServico); $m++) {
                $minutosOcupados[$m] = true;
            }
        }

        $slots = [];
        if ($turnos) {
            foreach ($turnos as $turno) {
                $ini   = explode(':', $turno['inicio']);
                $fim   = explode(':', $turno['fim']);
                $start = ($ini[0] * 60) + $ini[1];
                $end   = ($fim[0] * 60) + $fim[1];

                for ($time = $start; $time <= ($end - $duracaoServico); $time += 30) {
                    $livre = true;
                    for ($check = $time; $check < ($time + $duracaoServico); $check++) {
                        if (isset($minutosOcupados[$check])) {
                            $livre = false;
                            break;
                        }
                    }
                    if ($livre) {
                        $slots[] = str_pad(floor($time / 60), 2, '0', STR_PAD_LEFT)
                                 . ':' .
                                   str_pad($time % 60, 2, '0', STR_PAD_LEFT);
                    }
                }
            }
        }
        echo json_encode($slots);
        exit;
    }

    // Buscar Cliente
    if ($_GET['action'] === 'buscar_cliente') {
        $telefone = preg_replace('/[^0-9]/', '', $_GET['telefone']);
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$telefone, $profissionalId]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            echo json_encode([
                'found'           => true,
                'nome'            => $cliente['nome'],
                'telefone'        => $cliente['telefone'],
                'data_nascimento' => $cliente['data_nascimento']
            ]);
        } else {
            echo json_encode(['found' => false]);
        }
        exit;
    }
    exit;
}

// =========================================================
// 3. PROCESSAR POST (AGENDAMENTO)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = $_POST['cliente_nome'] ?? '';
    $telefone   = preg_replace('/[^0-9]/', '', $_POST['cliente_telefone'] ?? '');
    $nascimento = !empty($_POST['cliente_nascimento']) ? $_POST['cliente_nascimento'] : null;
    $obs        = $_POST['cliente_obs'] ?? '';
    $servicoId  = $_POST['servico_id'] ?? null;
    $data       = $_POST['data_escolhida'] ?? '';
    $horario    = $_POST['horario_escolhido'] ?? '';

    $stmt = $pdo->prepare("SELECT nome, preco FROM servicos WHERE id = ?");
    $stmt->execute([$servicoId]);
    $servicoDados = $stmt->fetch();
    $servicoNome  = $servicoDados['nome']  ?? 'Serviço';
    $servicoValor = $servicoDados['preco'] ?? 0;

    if ($nome && $telefone && $data && $horario) {
        // Cliente
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? AND user_id = ?");
        $stmt->execute([$telefone, $profissionalId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $clienteId = $existing['id'];
            $pdo->prepare("UPDATE clientes SET nome=?, telefone=?, data_nascimento=? WHERE id=?")
                ->execute([$nome, $telefone, $nascimento, $clienteId]);
        } else {
            $pdo->prepare("INSERT INTO clientes (user_id, nome, telefone, data_nascimento)
                           VALUES (?, ?, ?, ?)")
                ->execute([$profissionalId, $nome, $telefone, $nascimento]);
            $clienteId = $pdo->lastInsertId();
        }

        // Agendamento
        $sql = "INSERT INTO agendamentos
            (user_id, cliente_id, cliente_nome, cliente_telefone, servico, valor, data_agendamento, horario, status, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)";
        $pdo->prepare($sql)->execute([
            $profissionalId,
            $clienteId,
            $nome,
            $telefone,
            $servicoNome,
            $servicoValor,
            $data,
            $horario,
            $obs
        ]);

        // Consumir estoque conforme cálculo vinculado ao serviço (usando servico_id direto)
        require_once __DIR__ . '/includes/estoque_helper.php';
        consumirEstoquePorServico($pdo, $profissionalId, (int)$servicoId);

        $page = basename($_SERVER['PHP_SELF']);
        header("Location: $page?user={$profissionalId}&ok=1");
        exit;
    }
}

// Serviços
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY nome ASC");
$stmt->execute([$profissionalId]);
$servicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($nomeEstabelecimento); ?> | Agendamento</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/main.css">

</head>
<body>

<div class="app-container">
    <div class="sidebar">
        <div class="business-logo">
            <?php if ($temFoto): ?>
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Logo" class="logo-img">
            <?php else: ?>
                <div class="logo-initial"><?php echo $iniciais; ?></div>
            <?php endif; ?>
        </div>

        <h1 class="business-name"><?php echo htmlspecialchars($nomeEstabelecimento); ?></h1>

        <?php if ($nomeEstabelecimento !== $nomeProfissional): ?>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:10px;">
                Responsável: <?php echo htmlspecialchars($nomeProfissional); ?>
            </p>
        <?php endif; ?>

        <p class="business-bio"><?php echo htmlspecialchars($biografia); ?></p>

        <?php if ($telefone): ?>
            <div class="info-pill">
                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($telefone); ?>
            </div>
        <?php endif; ?>

        <?php if ($enderecoCompleto): ?>
            <div class="info-pill">
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($enderecoCompleto); ?>
            </div>
        <?php endif; ?>

        <div id="bookingSummary"
             style="margin-top:auto; width:100%; background:white; padding:20px; border-radius:20px; border:1px solid #e2e8f0; text-align:left; display:none; box-shadow:var(--shadow-card);"
             class="step-screen">
            <div style="font-size:0.7rem; text-transform:uppercase; color:#94a3b8; font-weight:800; margin-bottom:10px; letter-spacing:1px;">
                Seu Agendamento
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-weight:700; font-size:1rem;">
                <span id="sumServico">...</span>
                <span id="sumPreco" style="color:var(--brand-color);">...</span>
            </div>
            <div style="font-size:0.9rem; color:#64748b;" id="sumDataHora"></div>
        </div>
    </div>

    <div class="main-content">
        <?php if ($sucesso): ?>
            <div style="text-align:center; padding:40px;">
                <i class="bi bi-check-circle-fill"
                   style="font-size:5rem; color:#10b981; margin-bottom:20px; display:block;"></i>
                <h2 class="card-title">Agendamento Confirmado!</h2>
                <p class="card-subtitle">
                    Serviço: <strong><?php echo htmlspecialchars($servicoNome ?? ''); ?></strong><br>
                    Data/Hora: <strong><?php echo htmlspecialchars($_POST['data_escolhida'] ?? ''); ?> <?php echo htmlspecialchars($_POST['horario_escolhido'] ?? ''); ?></strong>
                </p>
                <?php
                $whats = preg_replace('/[^0-9]/', '', $profissional['telefone'] ?? '');
                $msg = rawurlencode("Olá! Acabei de agendar o serviço: {$servicoNome} para {$_POST['data_escolhida']} às {$_POST['horario_escolhido']}. Obrigado!");
                ?>
                <?php if ($whats): ?>
                    <a href="https://wa.me/<?php echo $whats; ?>?text=<?php echo $msg; ?>"
                       target="_blank" class="btn-action" style="background:#25d366; color:white; margin-bottom:10px; display:inline-block;">
                        <i class="bi bi-whatsapp"></i> Confirmar pelo WhatsApp
                    </a>
                <?php endif; ?>
                <a href="?user=<?php echo $profissionalId; ?>" class="btn-action" style="text-decoration:none;">
                    Agendar Novamente
                </a>
            </div>
        <?php else: ?>

            <div class="step-progress">
                <div class="progress-line"></div>
                <div class="step-wrapper active" id="dot1">
                    <div class="step-dot">1</div>
                    <div class="step-label">Serviço</div>
                </div>
                <div class="step-wrapper" id="dot2">
                    <div class="step-dot">2</div>
                    <div class="step-label">Data</div>
                </div>
                <div class="step-wrapper" id="dot3">
                    <div class="step-dot">3</div>
                    <div class="step-label">Finalizar</div>
                </div>
            </div>

            <form method="POST" id="agendaForm" onsubmit="return startSubmit()">
                <input type="hidden" name="servico_id" id="inServicoId">
                <input type="hidden" name="data_escolhida" id="inData">
                <input type="hidden" name="horario_escolhido" id="inHorario">

                <div class="step-screen active" id="step1">
                    <h2 class="card-title">Selecione o serviço</h2>
                    <p class="card-subtitle">O que vamos fazer hoje?</p>

                    <div>
                        <?php foreach ($servicos as $s): ?>
                            <div class="service-card"
                                 onclick="selectService(this, '<?php echo $s['id']; ?>', '<?php echo $s['nome']; ?>', '<?php echo $s['preco']; ?>', '<?php echo $s['duracao']; ?>')">
                                <div>
                                    <h3 style="font-size:1rem; font-weight:700;"><?php echo htmlspecialchars($s['nome']); ?></h3>
                                    <div style="font-size:0.85rem; color:var(--text-muted);">
                                        <?php echo $s['duracao']; ?> min
                                    </div>
                                </div>
                                <div class="service-price">
                                    R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($servicos)): ?>
                            <div style="text-align:center; padding:30px; color:#999;">
                                Nenhum serviço disponível.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="step-screen" id="step2">
                    <button type="button" class="btn-back" onclick="goToStep(1)">
                        <i class="bi bi-arrow-left"></i> Escolher outro serviço
                    </button>
                    <h2 class="card-title">Escolha o horário</h2>
                    <p class="card-subtitle">Toque na data e veja os horários livres.</p>

                    <div style="margin-bottom:20px;">
                        <input type="date" id="dateInput" class="form-control"
                               onchange="fetchTimes()" style="padding:16px;">
                    </div>

                    <div id="loadingTimes" style="display:none; text-align:center; padding:20px; color:var(--brand-color);">
                        <i class="bi bi-arrow-repeat"
                           style="animation:spin 1s infinite; display:inline-block; font-size:1.5rem;"></i>
                    </div>

                    <div id="timesContainer" class="time-slots-grid"></div>
                    <div id="noTimesMsg"
                         style="display:none; text-align:center; color:#ef4444; margin-top:20px;">
                        Sem horários livres nesta data.
                    </div>
                </div>

                <div class="step-screen" id="step3">
                    <button type="button" class="btn-back" onclick="goToStep(2)">
                        <i class="bi bi-arrow-left"></i> Trocar horário
                    </button>
                    <h2 class="card-title">Seus dados</h2>
                    <p class="card-subtitle">Para confirmarmos sua reserva.</p>

                    <div class="form-group">
                        <label class="form-label">Celular / WhatsApp</label>
                        <input type="tel" name="cliente_telefone" id="telInput" class="form-control"
                               placeholder="(11) 99999-9999" maxlength="15"
                               oninput="maskPhone(this)" onblur="checkClient()">
                        <div id="cpfLoading"
                             style="display:none; font-size:0.85rem; color:var(--text-muted); margin-top:5px;">
                            Verificando...
                        </div>
                    </div>

                    <div id="welcomeCard"
                         style="display:none; background:var(--brand-light); padding:15px; border-radius:12px; color:var(--brand-color); align-items:center; gap:10px; margin-bottom:20px; border:1px solid var(--brand-color);">
                        <i class="bi bi-person-check-fill" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Olá, <span id="clientNameDisplay"></span>!</strong><br>
                            <small>Seus dados foram carregados.</small>
                        </div>
                    </div>

                    <div id="newClientFields" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="cliente_nome" id="nomeInput" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nascimento</label>
                            <input type="date" name="cliente_nascimento" id="nascInput"
                                   class="form-control">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <label class="form-label">Observação (Opcional)</label>
                        <textarea name="cliente_obs" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" id="btnConfirmar" class="btn-action" disabled>
                        Confirmar Agendamento
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    const PROF_ID      = <?php echo $profissionalId; ?>;
    const CURRENT_PAGE = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
    let currentServiceDuration = 0;

    function goToStep(step) {
        document.querySelectorAll('.step-screen').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        document.querySelectorAll('.step-wrapper').forEach((el, index) => {
            el.classList.remove('active', 'done');
            if (index + 1 === step) el.classList.add('active');
            if (index + 1 < step) el.classList.add('done');
        });
    }

    function selectService(el, id, nome, preco, duracao) {
        document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');

        document.getElementById('inServicoId').value = id;
        currentServiceDuration = parseInt(duracao);

        document.getElementById('bookingSummary').style.display = 'block';
        document.getElementById('sumServico').innerText = nome;
        document.getElementById('sumPreco').innerText =
            'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');

        document.getElementById('inData').value = '';
        document.getElementById('inHorario').value = '';
        document.getElementById('sumDataHora').innerText = '';

        setTimeout(() => goToStep(2), 200);
    }

    function fetchTimes() {
        const dateVal = document.getElementById('dateInput').value;
        if (!dateVal) return;

        const loader    = document.getElementById('loadingTimes');
        const container = document.getElementById('timesContainer');
        const noTimes   = document.getElementById('noTimesMsg');

        container.innerHTML = '';
        noTimes.style.display = 'none';
        loader.style.display = 'block';

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_horarios&data=${dateVal}&duracao=${currentServiceDuration}`)
            .then(res => res.json())
            .then(slots => {
                loader.style.display = 'none';

                if (!slots.length) {
                    noTimes.style.display = 'block';
                } else {
                    slots.forEach(time => {
                        const div = document.createElement('div');
                        div.className = 'time-slot';
                        div.innerText = time;
                        div.onclick = () => selectTime(div, time, dateVal);
                        container.appendChild(div);
                    });
                }
            });
    }

    function selectTime(el, time, dateVal) {
        document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
        el.classList.add('selected');

        document.getElementById('inHorario').value = time;
        document.getElementById('inData').value = dateVal;

        const [y, m, d] = dateVal.split('-');
        document.getElementById('sumDataHora').innerText = `${d}/${m}/${y} às ${time}`;

        setTimeout(() => goToStep(3), 200);
    }

    function checkClient() {
        const tel = document.getElementById('telInput').value.replace(/\D/g, '');
        const btn = document.getElementById('btnConfirmar');

        if (tel.length < 10) {
            btn.disabled = true;
            return;
        }

        document.getElementById('cpfLoading').style.display = 'block';

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_cliente&telefone=${tel}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('cpfLoading').style.display = 'none';

                const newFields = document.getElementById('newClientFields');
                const welcome   = document.getElementById('welcomeCard');
                const nomeIn    = document.getElementById('nomeInput');
                const telInput  = document.getElementById('telInput'); // Renomeado para clareza

                if (data.found) {
                    newFields.style.display = 'none';
                    welcome.style.display   = 'flex';

                    document.getElementById('clientNameDisplay').innerText = data.nome.split(' ')[0];
                    nomeIn.value = data.nome;
                    telInput.value  = data.telefone; // Apenas o principal é atualizado
                    document.getElementById('nascInput').value = data.data_nascimento;

                    nomeIn.removeAttribute('required');
                } else {
                    welcome.style.display   = 'none';
                    newFields.style.display = 'block';

                    nomeIn.value = '';
                    // A máscara de telefone já é aplicada pelo oninput,
                    // então apenas garantimos que o valor esteja lá
                    telInput.value = document.getElementById('telInput').value;

                    nomeIn.setAttribute('required', 'true');
                }

                btn.disabled = false;
            })
            .catch(() => {
                btn.disabled = false;
            });
    }

    function startSubmit() {
        const btn = document.getElementById('btnConfirmar');
        if (btn.disabled) return false;

        btn.innerHTML = '<span class="loading-spinner"></span> Processando...';
        btn.disabled = true;
        return true;
    }

    function maskCPF(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        i.value = v;
    }

    function maskPhone(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
        v = v.replace(/(\d)(\d{4})$/, "$1-$2");
        i.value = v;
    }

    // Data mínima = hoje
    document.getElementById('dateInput').min = new Date().toISOString().split("T")[0];
</script>
</body>
</html>
