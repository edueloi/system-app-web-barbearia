<?php
// pages/agenda/nota.php
// Sistema de Emissão de Notas - Agendamento ou Nota Livre

// ===============================================
// 1. VERIFICAÇÃO E INCLUSÃO DE DEPENDÊNCIAS
// ===============================================

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Usuário não autenticado.');
}
$userId = $_SESSION['user_id'];

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$notaUrl = $isProd ? '/nota' : '/karen_site/controle-salao/pages/agenda/nota.php';

// Redireciona URLs com .php em produção para versão limpa
if ($isProd && strpos($_SERVER['REQUEST_URI'], '.php') !== false) {
    $cleanUrl = str_replace('.php', '', $_SERVER['REQUEST_URI']);
    header("Location: {$cleanUrl}", true, 301);
    exit;
}

$autoload_path = '../../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    die('ERRO FATAL: O arquivo de dependências do Composer (autoload.php) não foi encontrado em: ' . $autoload_path . '. Certifique-se de que o Composer foi executado e os arquivos existem.');
}
require_once $autoload_path;

$db_path = '../../includes/db.php';
if (!file_exists($db_path)) {
    die('ERRO FATAL: O arquivo de conexão com o banco de dados (db.php) não foi encontrado em: ' . $db_path);
}
include $db_path;

use Dompdf\Dompdf;
use Dompdf\Options;

// ===============================================
// 2. VERIFICAÇÃO DE MODO (AGENDAMENTO OU LIVRE)
// ===============================================

$modoLivre = isset($_GET['livre']) || isset($_POST['nota_livre']);
$gerarPDF = isset($_GET['gerar']) || isset($_POST['gerar_pdf']);

// Se for para gerar PDF direto
if ($gerarPDF) {
    if ($modoLivre && isset($_POST['nota_livre'])) {
        // Nota Livre - usa dados do POST
        $idAgendamento = (int)($_POST['id_agendamento'] ?? 0);
        $notaData = [
            'tipo' => 'livre',
            'servico' => $_POST['servico'] ?? 'Serviço',
            'valor' => (float)str_replace(',', '.', $_POST['valor'] ?? '0'),
            'cliente_nome' => $_POST['cliente_nome'] ?? 'Cliente',
            'data_agendamento' => date('Y-m-d'),
            'horario' => date('H:i:s'),
            'observacoes' => $_POST['observacoes'] ?? '',
            'id_agendamento' => $idAgendamento
        ];
    } else {
        // Nota de Agendamento - busca do banco
        if (!isset($_GET['id'])) die('ID não especificado.');
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT a.*, c.email AS cliente_email FROM agendamentos a LEFT JOIN clientes c ON a.cliente_nome = c.nome AND a.user_id = c.user_id WHERE a.id = ? AND a.user_id = ?");
        $stmt->execute([$id, $userId]);
        $ag = $stmt->fetch();
        
        if (!$ag) die('Agendamento não encontrado.');
        
        $notaData = [
            'tipo' => 'agendamento',
            'id' => $id,
            'servico' => $ag['servico'],
            'valor' => $ag['valor'],
            'cliente_nome' => $ag['cliente_nome'],
            'data_agendamento' => $ag['data_agendamento'],
            'horario' => $ag['horario'],
            'observacoes' => $ag['observacoes'] ?? '',
            'status' => $ag['status'] ?? 'Confirmado'
        ];
    }
    
    // Gera o PDF e volta para agenda
    $agendaUrl = $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php';
    gerarPDF($notaData, $pdo, $userId, $agendaUrl);
    exit;
}

// ===============================================
// 3. INTERFACE DE SELEÇÃO
// ===============================================

if (!$modoLivre && !isset($_GET['id'])) {
    die('Agendamento não especificado.');
}

// Busca dados do agendamento (se não for modo livre)
$ag = null;
if (!$modoLivre) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT a.*, c.email AS cliente_email FROM agendamentos a LEFT JOIN clientes c ON a.cliente_nome = c.nome AND a.user_id = c.user_id WHERE a.id = ? AND a.user_id = ?");
    $stmt->execute([$id, $userId]);
    $ag = $stmt->fetch();
    
    if (!$ag) die('Agendamento não encontrado.');
}

// Busca serviços para o formulário livre
$servicos = $pdo->query("SELECT nome, preco FROM servicos WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();

// ============================
// Dados do profissional (usuario)
// ============================
$stmtUser = $pdo->prepare("SELECT nome, email, telefone, cep, endereco, numero, bairro, cidade, estado, estabelecimento, tipo_estabelecimento FROM usuarios WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$prof = $stmtUser->fetch();

$nomeProfissional = $prof['estabelecimento'] ?? $prof['nome'] ?? 'Estabelecimento';
$emailProfissional = $prof['email'] ?? '';
$telefoneProfissional = $prof['telefone'] ?? '';

$enderecoPartes = [];
if (!empty($prof['endereco'])) {
    $linha = $prof['endereco'];
    if (!empty($prof['numero'])) $linha .= ', ' . $prof['numero'];
    $enderecoPartes[] = $linha;
}
if (!empty($prof['bairro'])) $enderecoPartes[] = $prof['bairro'];
$cidadeEstado = trim(($prof['cidade'] ?? '') . (!empty($prof['estado']) ? ' - ' . $prof['estado'] : ''));
if (!empty($cidadeEstado)) $enderecoPartes[] = $cidadeEstado;
if (!empty($prof['cep'])) $enderecoPartes[] = 'CEP: ' . $prof['cep'];
$enderecoProfissional = implode(' • ', array_filter($enderecoPartes));

// ===============================================
// 4. EXIBE INTERFACE DE SELEÇÃO
// ===============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir Nota</title>
    <link rel="icon" type="image/png" href="<?php echo $isProd ? '/img/favicon.png' : '/karen_site/controle-salao/img/favicon.png'; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --bg-body: #f8fafc;
            --text-main: #0f172a;
            --text-light: #64748b;
            --white: #ffffff;
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--text-main);
            padding: 20px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 32px;
            animation: fadeIn 0.4s ease-out;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.03em;
        }
        
        .header p {
            color: var(--text-light);
            font-size: 0.9375rem;
            font-weight: 500;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .option-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 28px 24px;
            box-shadow: 0 4px 20px rgba(15,23,42,0.08);
            border: 3px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        .option-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .option-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(99,102,241,0.2);
            border-color: var(--primary);
        }
        
        .option-card:hover::before {
            opacity: 0.05;
        }
        
        .option-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.75rem;
            position: relative;
            z-index: 1;
        }
        
        .option-card:hover .option-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .option-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            text-align: center;
            color: var(--text-main);
            letter-spacing: -0.02em;
            position: relative;
            z-index: 1;
        }
        
        .option-card p {
            font-size: 0.875rem;
            color: var(--text-light);
            text-align: center;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }
        
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            box-shadow: 0 8px 32px rgba(15,23,42,0.12);
            animation: fadeIn 0.5s ease-out;
        }
        
        .form-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-card h2 i {
            font-size: 1.75rem;
            color: var(--primary);
        }
        
        .form-subtitle {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-md);
            font-size: 0.9375rem;
            font-family: inherit;
            font-weight: 500;
            background: #f8fafc;
            transition: all 0.2s ease;
            color: var(--text-main);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(99,102,241,0.5);
        }
        
        .btn-primary:active {
            transform: scale(0.98);
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            color: var(--primary);
            background: #f8fafc;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 640px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($modoLivre): ?>
            <!-- FORMULÁRIO NOTA LIVRE -->
            <a href="<?php echo $notaUrl . '?id=' . ($id ?? ($_GET['id'] ?? '')); ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Voltar para Opções
            </a>
            
            <div class="form-card">
                <h2>
                    <i class="bi bi-receipt-cutoff"></i>
                    Nota Livre
                </h2>
                <p class="form-subtitle">
                    Personalize os dados da nota conforme necessário
                </p>
                
                <form method="POST" action="?gerar=1">
                    <input type="hidden" name="nota_livre" value="1">
                    <input type="hidden" name="gerar_pdf" value="1">
                    <input type="hidden" name="id_agendamento" value="<?php echo $id ?? ($_GET['id'] ?? ''); ?>">
                    <input type="hidden" name="cliente_nome" value="<?php echo htmlspecialchars($ag['cliente_nome'] ?? ''); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Serviço Prestado</label>
                            <input type="text" name="servico" class="form-input" 
                                   value="<?php echo htmlspecialchars($ag['servico'] ?? ''); ?>" 
                                   list="servicosList" required placeholder="Digite o serviço">
                            <datalist id="servicosList">
                                <?php foreach($servicos as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['nome']); ?>" data-preco="<?php echo $s['preco']; ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" name="valor" class="form-input" 
                                   value="<?php echo $ag['valor'] ?? ''; ?>" 
                                   step="0.01" required placeholder="0,00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Observações Adicionais</label>
                        <textarea name="observacoes" class="form-textarea" 
                                  placeholder="Detalhes adicionais sobre o serviço..."><?php echo htmlspecialchars($ag['observacoes'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                        Gerar Nota em PDF
                    </button>
                </form>
            </div>
            
        <?php else: ?>
            <!-- TELA DE ESCOLHA -->
            <a href="<?php echo $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php'; ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Voltar para Agenda
            </a>
            
            <div class="header">
                <h1>📄 Emitir Nota de Serviço</h1>
                <p>Escolha o tipo de nota que deseja gerar</p>
            </div>
            
            <div class="cards-grid">
                <a href="?id=<?php echo $ag['id']; ?>&gerar=1" class="option-card">
                    <div class="option-icon">
                        <i class="bi bi-calendar-check" style="color: var(--primary);"></i>
                    </div>
                    <h2>Nota do Agendamento</h2>
                    <p>
                        <strong><?php echo htmlspecialchars($ag['servico']); ?></strong><br>
                        R$ <?php echo number_format($ag['valor'], 2, ',', '.'); ?><br>
                        Cliente: <?php echo htmlspecialchars($ag['cliente_nome']); ?>
                    </p>
                </a>
                
                <a href="?id=<?php echo $ag['id']; ?>&livre=1" class="option-card">
                    <div class="option-icon">
                        <i class="bi bi-receipt-cutoff" style="color: var(--secondary);"></i>
                    </div>
                    <h2>Nota Livre</h2>
                    <p>
                        Edite o serviço e o valor antes de emitir.<br>
                        Ideal para ajustes e serviços personalizados.
                    </p>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-preenche valor ao selecionar serviço
        const servicoInput = document.querySelector('input[name="servico"]');
        const valorInput = document.querySelector('input[name="valor"]');
        
        if (servicoInput && valorInput) {
            servicoInput.addEventListener('input', function() {
                const selected = document.querySelector(`datalist option[value="${this.value}"]`);
                if (selected && selected.dataset.preco) {
                    valorInput.value = selected.dataset.preco;
                }
            });
        }
    </script>
</body>
</html>

<?php
exit; // Não executa o resto do código se estiver mostrando a interface

// ===============================================
// 5. FUNÇÃO PARA GERAR PDF
// ===============================================
function gerarPDF($notaData, $pdo, $userId, $agendaUrl) {
    // Busca dados do profissional
    $stmtUser = $pdo->prepare("SELECT nome, email, telefone, cep, endereco, numero, bairro, cidade, estado, estabelecimento, tipo_estabelecimento FROM usuarios WHERE id = ? LIMIT 1");
    $stmtUser->execute([$userId]);
    $prof = $stmtUser->fetch();

    
    $nomeProfissional = $prof['estabelecimento'] ?? $prof['nome'] ?? 'Estabelecimento';
    $tipoEstabelecimento = $prof['tipo_estabelecimento'] ?? '';
    $emailProfissional = $prof['email'] ?? '';
    $telefoneProfissional = $prof['telefone'] ?? '';
    
    $enderecoPartes = [];
    if (!empty($prof['endereco'])) {
        $linha = $prof['endereco'];
        if (!empty($prof['numero'])) $linha .= ', ' . $prof['numero'];
        $enderecoPartes[] = $linha;
    }
    if (!empty($prof['bairro'])) $enderecoPartes[] = $prof['bairro'];
    $cidadeEstado = trim(($prof['cidade'] ?? '') . (!empty($prof['estado']) ? ' - ' . $prof['estado'] : ''));
    if (!empty($cidadeEstado)) $enderecoPartes[] = $cidadeEstado;
    if (!empty($prof['cep'])) $enderecoPartes[] = 'CEP: ' . $prof['cep'];
    $enderecoProfissional = implode(' • ', array_filter($enderecoPartes));
    
    // Formatações
    $valorServico = $notaData['valor'];
    $dataFormatada = date('d/m/Y', strtotime($notaData['data_agendamento']));
    $horaFormatada = date('H:i', strtotime($notaData['horario']));
    $emitidoEm = date('d/m/Y H:i');
    $statusAgendamento = $notaData['status'] ?? 'Confirmado';
    $notaNumero = str_pad($notaData['id'] ?? rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    $valorFormatado = number_format($valorServico, 2, ',', '.');
    $valorTotalComSimbolo = 'R$ ' . $valorFormatado;

    // Montagem do HTML
    $html = '<html><head><meta charset="UTF-8">';
    $html .= '<style>
    * {
        /* Fonte compacta, ideal para documentos fiscais */
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 9px; /* Reduzindo o tamanho da fonte */
        box-sizing: border-box;
    }
    body {
        margin: 0;
        padding: 0;
    }
    .nota-container {
        width: 100%;
        padding: 5px; /* Reduzindo o padding */
        border: 1px solid #000;
    }
    .box {
        border: 1px solid #000;
        margin-bottom: 5px; /* Reduzindo o espaço entre os blocos */
        padding: 3px 5px;
    }
    .box-title {
        font-size: 10px;
        font-weight: bold;
        background-color: #e0e0e0;
        padding: 2px 0;
        margin: -3px -5px 3px -5px; /* Ajusta para preencher a largura */
        text-align: center;
        border-bottom: 1px solid #000;
        text-transform: uppercase;
    }
    .data-line {
        line-height: 1.2;
    }
    .label {
        font-weight: bold;
        padding-right: 3px;
    }
    /* Header Principal (Prestador/Nota) */
    .header-table {
        width: 100%;
        border-collapse: collapse;
    }
    .header-table td {
        border: 1px solid #000;
        padding: 4px;
        vertical-align: top;
    }
    .company-name {
        font-size: 11px;
        font-weight: bold;
        margin: 0 0 1px 0;
    }
    .doc-info-title {
        font-size: 10px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 2px;
        background-color: #f0f0f0;
    }
    
    /* Tabela de Serviços */
    .service-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    .service-table th, .service-table td {
        border: 1px solid #000;
        padding: 3px;
        text-align: left;
        font-size: 8.5px; /* Fonte menor para os itens */
    }
    .service-table th {
        background-color: #e0e0e0;
        text-transform: uppercase;
        font-size: 8.5px;
        text-align: center;
    }
    .col-cod, .col-qtd { width: 5%; text-align: center; }
    .col-valor-unit, .col-valor-total { width: 15%; text-align: right; }

    /* Totais */
    .totais-box {
        margin-top: 5px;
        padding-top: 4px;
        border-top: 1px solid #000;
        text-align: right;
    }
    .totais-box .label {
        font-size: 10px;
        font-weight: bold;
    }
    .totais-box .total-value {
        font-size: 11px;
        font-weight: bold;
        padding-left: 10px;
        color: #000;
    }

    /* Rodapé/Observações */
    .rodape {
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px dashed #000; /* Linha tracejada como em cupom */
        text-align: center;
        font-size: 7.5px;
        color: #333;
    }
    .observacoes-content {
        padding: 0;
        margin: 0;
        font-size: 8.5px;
    }
</style>';
$html .= '</head><body>';

$html .= '<div class="nota-container">';

// 1. CABEÇALHO PRINCIPAL (Prestador de Serviço e Dados da Nota)
$html .= '<table class="header-table" cellspacing="0" cellpadding="0">
            <tr>
                <td style="width: 65%;">
                    <p class="company-name">' . htmlspecialchars($nomeProfissional) . '</p>
                    <div class="data-line" style="font-weight: bold; color: #555;">' . (!empty($tipoEstabelecimento) ? htmlspecialchars($tipoEstabelecimento) : 'Atendimento de Beleza & Cuidados Pessoais') . '</div>';
if (!empty($enderecoProfissional)) {
    $html .= '              <div class="data-line">' . htmlspecialchars($enderecoProfissional) . '</div>';
}
if (!empty($telefoneProfissional) || !empty($emailProfissional)) {
    $contatosLinha = [];
    if (!empty($telefoneProfissional)) { $contatosLinha[] = 'Tel: ' . htmlspecialchars($telefoneProfissional); }
    if (!empty($emailProfissional)) { $contatosLinha[] = 'Email: ' . htmlspecialchars($emailProfissional); }
    $html .= '              <div class="data-line">' . implode(' | ', $contatosLinha) . '</div>';
}
$html .= '              <div class="data-line"><span class="label">Status do Agendamento:</span> ' . htmlspecialchars($statusAgendamento) . '</div>
                </td>
                <td style="width: 35%; text-align: right;">
                    <p class="doc-info-title">NOTA DE SERVIÇO</p>
                    <div class="data-line"><span class="label">Nº Nota:</span> ' . $notaNumero . '</div>
                    <div class="data-line"><span class="label">Emissão:</span> ' . $emitidoEm . '</div>
                    <div class="data-line"><span class="label">Agendamento:</span> ' . $dataFormatada . ' ' . $horaFormatada . '</div>
                </td>
            </tr>
        </table>';

    // 2. DADOS DO CLIENTE
    $html .= '<div class="box">
                <div class="box-title">Dados do Cliente (Tomador)</div>
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td colspan="2"><div class="data-line"><span class="label">Nome:</span> ' . htmlspecialchars($notaData['cliente_nome']) . '</div></td>
                    </tr>
                </table>
              </div>';

    // 3. DETALHES DO SERVIÇO
    $html .= '<div class="box">
                <div class="box-title">Detalhamento dos Serviços Prestados</div>
                <table class="service-table" cellspacing="0" cellpadding="0">
                    <thead>
                        <tr>
                            <th class="col-cod">CÓD.</th>
                            <th>DESCRIÇÃO DO SERVIÇO</th>
                            <th class="col-qtd">QTD.</th>
                            <th class="col-valor-unit">UNITÁRIO (R$)</th>
                            <th class="col-valor-total">VALOR TOTAL (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="col-cod">1</td>
                            <td>' . htmlspecialchars($notaData['servico']) . '</td>
                            <td class="col-qtd">1</td>
                            <td class="col-valor-unit">' . $valorFormatado . '</td>
                            <td class="col-valor-total">' . $valorFormatado . '</td>
                        </tr>
                    </tbody>
                </table>
              </div>';

    // 4. TOTAIS
    $html .= '<div class="totais-box">
                <span class="label">VALOR TOTAL DOS SERVIÇOS:</span>
                <span class="total-value">' . $valorTotalComSimbolo . '</span>
              </div>';

    // 5. OBSERVAÇÕES
    if (!empty($notaData['observacoes'])) {
        $html .= '<div class="box">
                    <div class="box-title">Observações do Serviço</div>
                    <div class="observacoes-content">' . nl2br(htmlspecialchars($notaData['observacoes'])) . '</div>
                  </div>';
    }

    // 6. RODAPÉ
    $html .= '<div class="rodape">
                **COMPROVANTE DE ' . ($notaData['tipo'] === 'livre' ? 'SERVIÇO PRESTADO' : 'AGENDAMENTO/SERVIÇO') . '**
                <br>Este documento não possui validade de Nota Fiscal Eletrônica (NF-e) ou Cupom Fiscal regulamentado.
                <br>Emitido eletronicamente em ' . $emitidoEm . '.
              </div>';

    $html .= '</div></body></html>';

    // Gera PDF
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A5', 'portrait');
    $dompdf->render();
    
    $fileName = 'nota-' . ($notaData['tipo'] === 'livre' ? 'livre' : 'agendamento') . '-' . $notaNumero . '.pdf';
    
    // Redireciona de volta para agenda após download
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Gerando Nota...</title>
        <link rel="icon" type="image/png" href="<?php echo ($isProd ?? false) ? '/img/favicon.png' : '/karen_site/controle-salao/img/favicon.png'; ?>">
        <style>
            body {
                font-family: Inter, Arial, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .loader {
                text-align: center;
                color: white;
            }
            .spinner {
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            h2 { margin: 0 0 10px 0; font-weight: 700; }
            p { opacity: 0.9; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="loader">
            <div class="spinner"></div>
            <h2>✓ Nota Gerada com Sucesso!</h2>
            <p>Redirecionando de volta para agenda...</p>
        </div>
        <script>
            // Força download do PDF
            const pdfData = '<?php echo base64_encode($dompdf->output()); ?>';
            const byteCharacters = atob(pdfData);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], {type: 'application/pdf'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = '<?php echo $fileName; ?>';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            
            // Redireciona após 1.5 segundos
            setTimeout(function() {
                window.location.href = '<?php echo $agendaUrl; ?>?view=day&data=<?php echo date('Y-m-d'); ?>';
            }, 1500);
        </script>
    </body>
    </html>
    <?php
}
?>